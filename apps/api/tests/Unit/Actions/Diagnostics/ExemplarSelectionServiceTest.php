<?php

namespace Tests\Unit\Actions\Diagnostics;

use Fluxio\Actions\Diagnostics\Examples\DTO\ScoredExemplar;
use Fluxio\Actions\Diagnostics\Examples\ExemplarSelectionService;
use Fluxio\Actions\Examples\IntentExample;
use Tests\TestCase;

/**
 * Slice A3.1 — deterministic exemplar selector.
 *
 * Pure, in-memory checks of the scorer/ranker (no LLM, no registry, no proposals). The selector
 * receives already-known evaluation metadata (intent + asserted entity keys) and ranks candidate
 * IntentExamples by a transparent additive score — same intent, slot overlap, template preference —
 * with a diversity guard and stable ordering. The registry-backed `selectForLocale` is checked
 * against the shipped seed data for the same-locale hard filter.
 */
class ExemplarSelectionServiceTest extends TestCase
{
    private function service(): ExemplarSelectionService
    {
        return $this->app->make(ExemplarSelectionService::class);
    }

    /**
     * @param  array<string, string>  $slotKeys  placeholder-name => entity_key
     */
    private function template(string $id, string $intent, array $slotKeys): IntentExample
    {
        $slots = [];
        $placeholders = [];
        foreach ($slotKeys as $name => $entityKey) {
            $slots[$name] = ['placeholder' => '{'.$name.'}', 'entity_key' => $entityKey];
            $placeholders[] = '{'.$name.'}';
        }

        return new IntentExample(
            id: $id,
            locale: 'it',
            intent: $intent,
            text: null,
            template: 'do '.implode(' ', $placeholders),
            slots: $slots,
        );
    }

    private function literal(string $id, string $intent): IntentExample
    {
        return new IntentExample(id: $id, locale: 'it', intent: $intent, text: 'a literal phrase', template: null);
    }

    /** @return list<string> */
    private function ids(array $examples): array
    {
        return array_map(static fn (IntentExample $e): string => $e->id, $examples);
    }

    // ── intent preference ─────────────────────────────────────────────────────

    public function test_same_intent_outranks_a_generic_intent(): void
    {
        $candidates = [
            $this->template('create', 'create_task', ['lead' => 'lead']),
            $this->template('assign', 'assign_lead', ['lead' => 'lead', 'assignee' => 'assignee']),
        ];

        $ranked = $this->service()->rank($candidates, 'assign_lead', ['lead', 'assignee']);

        $this->assertSame('assign', $ranked[0]->example->id);
        $this->assertTrue($ranked[0]->intentMatch);
        $this->assertFalse($ranked[1]->intentMatch);
        $this->assertGreaterThan($ranked[1]->score, $ranked[0]->score);
    }

    public function test_select_prefers_assign_lead_examples_over_create_task(): void
    {
        $candidates = [
            $this->template('create', 'create_task', ['lead' => 'lead']),
            $this->template('assign', 'assign_lead', ['lead' => 'lead', 'assignee' => 'assignee']),
        ];

        $selected = $this->service()->selectFrom($candidates, 'assign_lead', ['lead', 'assignee'], 1);

        $this->assertSame(['assign'], $this->ids($selected));
    }

    // ── slot overlap scoring ──────────────────────────────────────────────────

    public function test_slot_overlap_breaks_ties_within_the_same_intent(): void
    {
        $full = $this->template('full', 'schedule_meeting', ['lead' => 'lead', 'date' => 'date', 'time' => 'time']);
        $thin = $this->template('thin', 'schedule_meeting', ['lead' => 'lead']);

        $ranked = $this->service()->rank([$thin, $full], 'schedule_meeting', ['lead', 'date', 'time']);

        $this->assertSame('full', $ranked[0]->example->id);
        $this->assertSame(3, $ranked[0]->slotOverlap);
        $this->assertSame(1, $ranked[1]->slotOverlap);
        $this->assertEqualsCanonicalizing(['lead', 'date', 'time'], $ranked[0]->matchedSlots);
    }

    public function test_score_components_are_transparent(): void
    {
        $candidate = $this->template('m', 'schedule_meeting', ['lead' => 'lead', 'date' => 'date']);

        $scored = $this->service()->rank([$candidate], 'schedule_meeting', ['lead', 'date'])[0];

        $this->assertInstanceOf(ScoredExemplar::class, $scored);
        // 100 (intent) + 2*10 (slot overlap) + 1 (template bonus) = 121.
        $expected = ExemplarSelectionService::INTENT_MATCH_SCORE
            + 2 * ExemplarSelectionService::SLOT_OVERLAP_WEIGHT
            + ExemplarSelectionService::TEMPLATE_BONUS;
        $this->assertSame($expected, $scored->score);
    }

    // ── template preference ───────────────────────────────────────────────────

    public function test_template_outranks_a_literal_of_equal_relevance(): void
    {
        $literal = $this->literal('lit', 'create_task');
        $template = $this->template('tpl', 'create_task', ['lead' => 'lead']);

        // No asserted slot keys → both differ only by the template bonus.
        $ranked = $this->service()->rank([$literal, $template], 'create_task', []);

        $this->assertSame('tpl', $ranked[0]->example->id);
        $this->assertTrue($ranked[0]->isTemplate);
        $this->assertFalse($ranked[1]->isTemplate);
    }

    // ── ranking stability ─────────────────────────────────────────────────────

    public function test_equal_scores_preserve_original_order_and_are_stable(): void
    {
        $candidates = [
            $this->template('a', 'create_task', ['lead' => 'lead']),
            $this->template('b', 'create_task', ['lead' => 'lead']),
            $this->template('c', 'create_task', ['lead' => 'lead']),
        ];

        $first = $this->ids(array_map(static fn (ScoredExemplar $s): IntentExample => $s->example, $this->service()->rank($candidates, 'create_task', ['lead'])));
        $second = $this->ids(array_map(static fn (ScoredExemplar $s): IntentExample => $s->example, $this->service()->rank($candidates, 'create_task', ['lead'])));

        $this->assertSame(['a', 'b', 'c'], $first);
        $this->assertSame($first, $second);
    }

    // ── diversity behavior ────────────────────────────────────────────────────

    public function test_diversity_guard_drops_near_duplicates(): void
    {
        $candidates = [
            $this->template('dup-1', 'create_task', ['lead' => 'lead']),
            $this->template('dup-2', 'create_task', ['lead' => 'lead']), // same intent + slot shape
            $this->template('assign', 'assign_lead', ['lead' => 'lead', 'assignee' => 'assignee']),
        ];

        $selected = $this->service()->selectFrom($candidates, 'create_task', ['lead']);

        // Only the first of the duplicate signatures survives; the distinct intent is kept.
        $this->assertSame(['dup-1', 'assign'], $this->ids($selected));
    }

    // ── locale preference (registry-backed, shipped seed data) ────────────────

    public function test_select_for_locale_only_returns_same_locale_candidates(): void
    {
        $it = $this->service()->selectForLocale('it', 'create_task', ['lead']);
        $en = $this->service()->selectForLocale('en', 'create_task', ['lead']);

        $this->assertNotEmpty($it);
        $this->assertNotEmpty($en);
        $this->assertTrue(collect($it)->every(fn (IntentExample $e): bool => $e->locale === 'it'));
        $this->assertTrue(collect($en)->every(fn (IntentExample $e): bool => $e->locale === 'en'));
        // The relevant intent ranks first within the locale.
        $this->assertSame('create_task', $it[0]->intent);
    }

    public function test_limit_caps_the_selection(): void
    {
        $candidates = [
            $this->template('a', 'create_task', ['lead' => 'lead']),
            $this->template('b', 'schedule_call', ['lead' => 'lead']),
            $this->template('c', 'assign_lead', ['lead' => 'lead', 'assignee' => 'assignee']),
        ];

        $this->assertCount(2, $this->service()->selectFrom($candidates, 'assign_lead', ['lead'], 2));
    }
}
