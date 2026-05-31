<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\Diagnostics\Refinement\RefinementCorpusLoader;
use Fluxio\Actions\DTO\Ambiguity\AmbiguityDirective;
use Fluxio\Actions\DTO\Ambiguity\AttributeSelector;
use Fluxio\Actions\DTO\Ambiguity\SemanticAmbiguityClarification;
use Fluxio\Actions\DTO\NormalizedMutation;
use Fluxio\Actions\DTO\SemanticRefinementMutation;
use Fluxio\Actions\Enums\AmbiguitySelectorKind;
use Fluxio\Actions\Enums\ResolutionOutcomeType;
use Fluxio\Actions\Enums\SemanticMutationType;
use Fluxio\Actions\Exceptions\CannotLowerAmbiguityClarificationException;
use Fluxio\Actions\Exceptions\CannotLowerSemanticRefinementException;
use Fluxio\Actions\Support\AmbiguityDirectiveLowerer;
use Fluxio\Actions\Support\AmbiguityResolver;
use Fluxio\Actions\Support\DefaultIntentCapabilities;
use Fluxio\Actions\Support\SemanticRefinementLowerer;
use PHPUnit\Framework\TestCase;

/**
 * Phase 8D.1 — Semantic vocabulary guardrails.
 *
 * Cheap, protective tests that make semantic-vocabulary drift VISIBLE before the
 * live runtime arrow flip. They assert consistency between the semantic mutation
 * vocabulary (SemanticMutationType) and its foundation pieces — the lowerer, the
 * refinement corpus, the ambiguity selector boundary, the capability surface, and
 * the frontend renderer.
 *
 * These add NO runtime behavior and migrate nothing. They are a tripwire: when
 * the vocabulary grows or a foundation falls out of sync, a test fails loudly so
 * the gap is addressed deliberately rather than silently.
 */
class SemanticVocabularyGuardrailTest extends TestCase
{
    /**
     * Semantic mutation types that MUST lower into a structural NormalizedMutation.
     *
     * @var list<SemanticMutationType>
     */
    private const LOWERABLE = [
        SemanticMutationType::ReplaceTime,
        SemanticMutationType::ReplaceDate,
        SemanticMutationType::ShiftTime,
        SemanticMutationType::AddParticipant,
        SemanticMutationType::RemoveParticipant,
        SemanticMutationType::ReplaceParticipant,
    ];

    /**
     * Semantic mutation types that MUST NOT lower (no candidate semantic effect).
     *
     * @var list<SemanticMutationType>
     */
    private const NON_LOWERABLE = [
        SemanticMutationType::Unknown,
    ];

    /**
     * A valid semantic mutation fixture for every enum case. The match has an arm
     * per case, so adding a new SemanticMutationType without categorizing it here
     * fails with an UnhandledMatchError — the first tripwire.
     */
    private function fixtureFor(SemanticMutationType $type): SemanticRefinementMutation
    {
        return match ($type) {
            SemanticMutationType::ReplaceTime => new SemanticRefinementMutation($type, ['value' => '11:00']),
            SemanticMutationType::ReplaceDate => new SemanticRefinementMutation($type, ['value' => '2026-06-05']),
            SemanticMutationType::ShiftTime => new SemanticRefinementMutation($type, ['amount' => 30, 'unit' => 'minutes', 'direction' => 'later']),
            SemanticMutationType::AddParticipant => new SemanticRefinementMutation($type, ['value' => 'Marco']),
            SemanticMutationType::RemoveParticipant => new SemanticRefinementMutation($type, target: 'Mario'),
            SemanticMutationType::ReplaceParticipant => new SemanticRefinementMutation($type, ['value' => 'Mario'], target: 'Marco'),
            SemanticMutationType::Unknown => new SemanticRefinementMutation($type),
        };
    }

    // ── Guardrail 1: lowerer coverage ────────────────────────────────────────

    public function test_lowerable_and_non_lowerable_sets_partition_every_enum_case(): void
    {
        $declared = array_merge(self::LOWERABLE, self::NON_LOWERABLE);

        // Every enum case must be categorized exactly once. A new case (lowerable
        // or not) forces an explicit decision here before this guardrail passes.
        $this->assertCount(count(SemanticMutationType::cases()), $declared);
        foreach (SemanticMutationType::cases() as $case) {
            $this->assertContains($case, $declared, "SemanticMutationType::{$case->name} is not categorized as lowerable or non-lowerable.");
        }
    }

    public function test_every_lowerable_type_lowers_and_carries_its_semantic_type(): void
    {
        $lowerer = new SemanticRefinementLowerer;

        foreach (self::LOWERABLE as $type) {
            $lowered = $lowerer->lower($this->fixtureFor($type));

            $this->assertInstanceOf(NormalizedMutation::class, $lowered, "{$type->value} did not lower.");
            // The arrow points down: the lowered structural mutation carries the
            // semantic type as input, not re-derived from structure.
            $this->assertSame($type, $lowered->semanticType(), "{$type->value} lost its semantic type through lowering.");
        }
    }

    public function test_unknown_must_not_lower(): void
    {
        $this->expectException(CannotLowerSemanticRefinementException::class);
        (new SemanticRefinementLowerer)->lower($this->fixtureFor(SemanticMutationType::Unknown));
    }

    // ── Guardrail 2: corpus coverage ─────────────────────────────────────────

    public function test_every_lowerable_type_has_a_corpus_case(): void
    {
        $cases = (new RefinementCorpusLoader)->load(RefinementCorpusLoader::defaultCorpusPath());

        $covered = [];
        foreach ($cases as $case) {
            foreach ($case->expectedSemanticTypes as $type) {
                $covered[$type] = true;
            }
        }

        foreach (self::LOWERABLE as $type) {
            $this->assertArrayHasKey(
                $type->value,
                $covered,
                "No refinement corpus case asserts semantic type [{$type->value}]; the corpus must stay the executable spec.",
            );
        }
    }

    // ── Guardrail 3: ambiguity clarification boundary ────────────────────────

    public function test_ambiguity_selector_kinds_are_the_closed_set(): void
    {
        $kinds = array_map(fn (AmbiguitySelectorKind $k) => $k->value, AmbiguitySelectorKind::cases());
        sort($kinds);

        $this->assertSame(['attribute', 'label', 'ordinal'], $kinds);
    }

    public function test_ambiguity_lowerer_rejects_identity_dimensions(): void
    {
        $lowerer = new AmbiguityDirectiveLowerer;

        foreach (['id', 'candidate_id', 'selected_candidate_id'] as $dimension) {
            try {
                $lowerer->lower(new SemanticAmbiguityClarification('lead', new AttributeSelector($dimension, '7')));
                $this->fail("Identity dimension [{$dimension}] was not rejected.");
            } catch (CannotLowerAmbiguityClarificationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_resolver_can_produce_each_outcome_type(): void
    {
        $resolver = new AmbiguityResolver;
        $candidates = [
            ['id' => 7, 'label' => 'Rossi SRL', 'type' => 'company'],
            ['id' => 1, 'label' => 'Mario Rossi', 'type' => 'person'],
            ['id' => 9, 'label' => 'Studio Rossi', 'type' => 'company'],
        ];

        $resolved = $resolver->resolve(new AmbiguityDirective('lead', new AttributeSelector('type', 'person')), $candidates);
        $narrowed = $resolver->resolve(new AmbiguityDirective('lead', new AttributeSelector('type', 'company')), $candidates);
        $unresolved = $resolver->resolve(new AmbiguityDirective('lead', new AttributeSelector('type', 'ghost')), $candidates);

        // Every ResolutionOutcomeType must stay reachable.
        $this->assertSame(ResolutionOutcomeType::Resolved, $resolved->type);
        $this->assertSame(ResolutionOutcomeType::Narrowed, $narrowed->type);
        $this->assertSame(ResolutionOutcomeType::Unresolved, $unresolved->type);
        $this->assertCount(3, ResolutionOutcomeType::cases());
    }

    // ── Guardrail 4: capability surface (conservative — schedule_meeting only) ─

    public function test_schedule_meeting_capability_surface_maps_to_semantic_families(): void
    {
        $capability = null;
        foreach (DefaultIntentCapabilities::all() as $declared) {
            if ($declared->intent === 'schedule_meeting') {
                $capability = $declared;
                break;
            }
        }

        $this->assertNotNull($capability, 'schedule_meeting capability is missing.');

        // replace_date / replace_time: a scalar replace covering date and time.
        $this->assertTrue(
            $this->hasMutation($capability->mutations, 'replace', collection: false, requiredFields: ['date', 'time']),
            'schedule_meeting must allow scalar replace of date/time (replace_date / replace_time).',
        );

        // add_participant: participants append (collection).
        $this->assertTrue(
            $this->hasMutation($capability->mutations, 'append', collection: true, requiredFields: ['participants']),
            'schedule_meeting must allow participants append (add_participant).',
        );

        // remove_participant: participants remove (collection).
        $this->assertTrue(
            $this->hasMutation($capability->mutations, 'remove', collection: true, requiredFields: ['participants']),
            'schedule_meeting must allow participants remove (remove_participant).',
        );

        // replace_participant: targeted participants replace (collection).
        $this->assertTrue(
            $this->hasMutation($capability->mutations, 'replace', collection: true, requiredFields: ['participants']),
            'schedule_meeting must allow participants replace (replace_participant).',
        );
    }

    /**
     * @param  list<\Fluxio\Actions\DTO\MutationCapability>  $mutations
     * @param  list<string>  $requiredFields
     */
    private function hasMutation(array $mutations, string $operation, bool $collection, array $requiredFields): bool
    {
        foreach ($mutations as $mutation) {
            if (
                $mutation->operation === $operation
                && $mutation->collection === $collection
                && array_diff($requiredFields, $mutation->fields) === []
            ) {
                return true;
            }
        }

        return false;
    }

    // ── Guardrail 5: frontend rendering drift (source-level grep) ────────────

    public function test_frontend_last_refinement_panel_supports_every_lowerable_type(): void
    {
        // Normalize the shipped-corpus path (it contains relative ../ segments),
        // then walk up: <root>/packages/Actions/evaluation/corpus.json → <root>.
        $corpusPath = realpath(RefinementCorpusLoader::defaultCorpusPath());
        $repoRoot = $corpusPath !== false ? dirname($corpusPath, 4) : '';
        $panel = $repoRoot.'/apps/web/app/components/proposal/LastRefinementPanel.vue';

        if ($repoRoot === '' || ! is_file($panel)) {
            $this->markTestSkipped("LastRefinementPanel.vue not found at [{$panel}]; frontend rendering guardrail skipped.");
        }

        $source = (string) file_get_contents($panel);

        foreach (self::LOWERABLE as $type) {
            $this->assertStringContainsString(
                $type->value,
                $source,
                "LastRefinementPanel.vue does not reference semantic type [{$type->value}]; frontend rendering may be drifting from the backend vocabulary.",
            );
        }
    }
}
