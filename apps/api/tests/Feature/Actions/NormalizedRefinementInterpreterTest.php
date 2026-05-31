<?php

namespace Tests\Feature\Actions;

use Carbon\Carbon;
use Fluxio\Actions\DTO\Ambiguity\SemanticAmbiguityClarification;
use Fluxio\Actions\DTO\NormalizedMutation;
use Fluxio\Actions\DTO\SemanticRefinementMutation;
use Fluxio\Actions\Interpreters\RuleBasedRefinementInterpreter;
use Fluxio\Actions\Support\SemanticRefinementLowerer;
use Tests\TestCase;

/**
 * Verifies the NL → structured-mutation boundary that ActionProposalRefinementService
 * applies. Post Phase 8D.2 that boundary is interpret() (Semantic Refinement IR) +
 * lowering; these tests therefore assert on the LOWERED structural NormalizedMutation,
 * mirroring the live service seam exactly. Priority replace/clear are not yet migrated
 * and pass through as structural mutations unchanged.
 */
class NormalizedRefinementInterpreterTest extends TestCase
{
    private RuleBasedRefinementInterpreter $interpreter;

    private SemanticRefinementLowerer $lowerer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-05-10 08:00:00');
        $this->interpreter = $this->app->make(RuleBasedRefinementInterpreter::class);
        $this->lowerer = new SemanticRefinementLowerer;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    /**
     * Interpret, then lower the Semantic Refinement IR into structural FIELD
     * mutations — the field side of the refinement seam. Ambiguity clarifications
     * (Phase 8D.3) are filtered out here; this helper protects the field-mutation
     * boundary only (ambiguity selector extraction is covered by
     * AmbiguitySelectorExtractionTest).
     *
     * @return NormalizedMutation[]
     */
    private function interpretStructural(string $text): array
    {
        $fieldOps = array_filter(
            $this->interpreter->interpret($text),
            fn ($op) => ! $op instanceof SemanticAmbiguityClarification,
        );

        return array_map(
            fn ($op) => $op instanceof SemanticRefinementMutation ? $this->lowerer->lower($op) : $op,
            array_values($fieldOps),
        );
    }

    // ── Time mutations ────────────────────────────────────────────────────────

    public function test_at_colon_time_produces_single_time_mutation(): void
    {
        $mutations = $this->interpretStructural('At 10:30');

        $this->assertCount(1, $mutations);
        $this->assertEquals('time', $mutations[0]->field);
        $this->assertEquals('Time', $mutations[0]->label);
        $this->assertEquals('10:30', $mutations[0]->value);
        $this->assertEquals('detected', $mutations[0]->source);
    }

    public function test_at_dot_time_produces_single_time_mutation(): void
    {
        $mutations = $this->interpretStructural('At 10.30');

        $this->assertCount(1, $mutations);
        $this->assertEquals('time', $mutations[0]->field);
        $this->assertEquals('10:30', $mutations[0]->value);
    }

    public function test_at_hour_only_defaults_to_zero_minutes(): void
    {
        $mutations = $this->interpretStructural('At 9');

        $this->assertCount(1, $mutations);
        $this->assertEquals('09:00', $mutations[0]->value);
    }

    public function test_at_pm_time_converts_to_24h(): void
    {
        $mutations = $this->interpretStructural('At 3pm');

        $this->assertCount(1, $mutations);
        $this->assertEquals('15:00', $mutations[0]->value);
    }

    public function test_at_am_noon_converts_to_midnight(): void
    {
        $mutations = $this->interpretStructural('At 12am');

        $this->assertCount(1, $mutations);
        $this->assertEquals('00:00', $mutations[0]->value);
    }

    public function test_morning_keyword_produces_09_00(): void
    {
        $mutations = $this->interpretStructural('morning');

        $timeMutations = array_filter($mutations, fn ($m) => $m->field === 'time');
        $this->assertCount(1, $timeMutations);
        $this->assertEquals('09:00', array_values($timeMutations)[0]->value);
    }

    // ── Date mutations ────────────────────────────────────────────────────────

    public function test_tomorrow_produces_single_date_mutation(): void
    {
        $mutations = $this->interpretStructural('Tomorrow');

        $this->assertCount(1, $mutations);
        $this->assertEquals('date', $mutations[0]->field);
        $this->assertEquals('Date', $mutations[0]->label);
        $this->assertEquals(now()->addDay()->toDateString(), $mutations[0]->value);
    }

    public function test_friday_keyword_produces_next_friday_date(): void
    {
        $mutations = $this->interpretStructural('Friday instead');

        $dateMutations = array_filter($mutations, fn ($m) => $m->field === 'date');
        $this->assertCount(1, $dateMutations);
        $this->assertEquals(Carbon::parse('next friday')->toDateString(), array_values($dateMutations)[0]->value);
    }

    // ── Combined mutations ────────────────────────────────────────────────────

    public function test_tomorrow_morning_produces_date_and_time_mutations(): void
    {
        $mutations = $this->interpretStructural('Tomorrow morning');

        $fields = array_map(fn ($m) => $m->field, $mutations);
        $this->assertContains('date', $fields);
        $this->assertContains('time', $fields);
        $this->assertCount(2, $mutations);
    }

    public function test_tomorrow_at_nine_produces_date_and_time_mutations(): void
    {
        $mutations = $this->interpretStructural('Tomorrow at 9');

        $fields = array_map(fn ($m) => $m->field, $mutations);
        $this->assertContains('date', $fields);
        $this->assertContains('time', $fields);
    }

    // ── Priority mutations ────────────────────────────────────────────────────

    public function test_high_priority_produces_priority_mutation(): void
    {
        $mutations = $this->interpretStructural('High priority');

        $this->assertCount(1, $mutations);
        $this->assertEquals('priority', $mutations[0]->field);
        $this->assertEquals('Priority', $mutations[0]->label);
        $this->assertEquals('high', $mutations[0]->value);
    }

    public function test_urgent_produces_high_priority_mutation(): void
    {
        $mutations = $this->interpretStructural('Urgent');

        $this->assertCount(1, $mutations);
        $this->assertEquals('priority', $mutations[0]->field);
        $this->assertEquals('high', $mutations[0]->value);
    }

    public function test_low_priority_produces_low_priority_mutation(): void
    {
        $mutations = $this->interpretStructural('Low priority');

        $this->assertCount(1, $mutations);
        $this->assertEquals('priority', $mutations[0]->field);
        $this->assertEquals('low', $mutations[0]->value);
    }

    // ── No-op cases ───────────────────────────────────────────────────────────

    public function test_unrecognized_text_produces_no_mutations(): void
    {
        $mutations = $this->interpretStructural('gibberish command xyz');

        $this->assertEmpty($mutations);
    }

    public function test_empty_string_produces_no_mutations(): void
    {
        $mutations = $this->interpretStructural('');

        $this->assertEmpty($mutations);
    }

    // ── Mutation DTO structure ────────────────────────────────────────────────

    public function test_mutation_source_defaults_to_detected(): void
    {
        $mutations = $this->interpretStructural('At 10:30');

        $this->assertEquals('detected', $mutations[0]->source);
    }

    public function test_mutation_confidence_defaults_to_one(): void
    {
        $mutations = $this->interpretStructural('At 10:30');

        $this->assertEquals(1.0, $mutations[0]->confidence);
    }

    // ── Mutation operations ───────────────────────────────────────────────────

    public function test_replace_mutations_carry_replace_operation(): void
    {
        foreach (['At 10:30', 'Tomorrow', 'High priority', 'Urgent', 'Low priority', 'Friday instead'] as $input) {
            $mutations = $this->interpretStructural($input);
            foreach ($mutations as $m) {
                $this->assertEquals('replace', $m->operation, "Expected 'replace' for input: {$input}");
            }
        }
    }

    // ── Clear operation ───────────────────────────────────────────────────────

    public function test_remove_priority_phrase_produces_clear_mutation(): void
    {
        $mutations = $this->interpretStructural('Remove priority');

        $this->assertCount(1, $mutations);
        $this->assertEquals('priority', $mutations[0]->field);
        $this->assertEquals('clear', $mutations[0]->operation);
        $this->assertNull($mutations[0]->value);
    }

    public function test_clear_priority_phrase_produces_clear_mutation(): void
    {
        $mutations = $this->interpretStructural('Clear priority');

        $this->assertCount(1, $mutations);
        $this->assertEquals('priority', $mutations[0]->field);
        $this->assertEquals('clear', $mutations[0]->operation);
    }

    public function test_no_priority_phrase_produces_clear_mutation(): void
    {
        $mutations = $this->interpretStructural('No priority');

        $this->assertCount(1, $mutations);
        $this->assertEquals('priority', $mutations[0]->field);
        $this->assertEquals('clear', $mutations[0]->operation);
    }

    public function test_clear_mutation_value_is_null(): void
    {
        $mutations = $this->interpretStructural('Remove priority');

        $this->assertNull($mutations[0]->value);
    }

    public function test_clear_mutation_source_is_detected(): void
    {
        $mutations = $this->interpretStructural('Remove priority');

        $this->assertEquals('detected', $mutations[0]->source);
    }

    public function test_clear_operation_takes_precedence_over_replace_for_same_field(): void
    {
        // "no priority" must produce clear, not a replace with value "normal" or similar
        $mutations = $this->interpretStructural('no priority');

        $this->assertCount(1, $mutations);
        $this->assertEquals('clear', $mutations[0]->operation);
    }

    // ── Proposal-local references ─────────────────────────────────────────────

    public function test_move_it_to_friday_produces_date_mutation(): void
    {
        // "it" is a proposal-local pronoun; date extraction still works via weekday matching
        $mutations = $this->interpretStructural('Move it to Friday');

        $dateMutations = array_filter($mutations, fn ($m) => $m->field === 'date');
        $this->assertCount(1, $dateMutations);
        $this->assertEquals(Carbon::parse('next friday')->toDateString(), array_values($dateMutations)[0]->value);
    }

    public function test_move_it_to_friday_operation_is_replace(): void
    {
        $mutations = $this->interpretStructural('Move it to Friday');

        $dateMutation = array_values(array_filter($mutations, fn ($m) => $m->field === 'date'))[0];
        $this->assertEquals('replace', $dateMutation->operation);
    }

    // ── Collection append ─────────────────────────────────────────────────────

    public function test_add_too_produces_append_mutation(): void
    {
        $mutations = $this->interpretStructural('Add Mario too');

        $this->assertCount(1, $mutations);
        $this->assertEquals('participants', $mutations[0]->field);
        $this->assertEquals('Participants', $mutations[0]->label);
        $this->assertEquals('append', $mutations[0]->operation);
        $this->assertEquals('Mario', $mutations[0]->value);
    }

    public function test_also_add_produces_append_mutation(): void
    {
        $mutations = $this->interpretStructural('Also add Marco');

        $this->assertCount(1, $mutations);
        $this->assertEquals('participants', $mutations[0]->field);
        $this->assertEquals('append', $mutations[0]->operation);
        $this->assertEquals('Marco', $mutations[0]->value);
    }

    public function test_append_mutation_target_is_null(): void
    {
        $mutations = $this->interpretStructural('Add Mario too');

        $this->assertNull($mutations[0]->target);
    }

    // ── Collection remove ─────────────────────────────────────────────────────

    public function test_remove_name_produces_remove_mutation(): void
    {
        $mutations = $this->interpretStructural('Remove Marco');

        $this->assertCount(1, $mutations);
        $this->assertEquals('participants', $mutations[0]->field);
        $this->assertEquals('remove', $mutations[0]->operation);
        $this->assertNull($mutations[0]->value);
    }

    public function test_remove_name_sets_target(): void
    {
        $mutations = $this->interpretStructural('Remove Marco');

        $this->assertEquals('Marco', $mutations[0]->target);
    }

    public function test_remove_priority_still_produces_clear_not_remove(): void
    {
        // Regression: "Remove priority" must remain a clear on priority, not remove on participants
        $mutations = $this->interpretStructural('Remove priority');

        $this->assertCount(1, $mutations);
        $this->assertEquals('priority', $mutations[0]->field);
        $this->assertEquals('clear', $mutations[0]->operation);
    }

    // ── Collection replace ────────────────────────────────────────────────────

    public function test_replace_x_with_y_produces_replace_mutation(): void
    {
        $mutations = $this->interpretStructural('Replace Luca with Marco');

        $this->assertCount(1, $mutations);
        $this->assertEquals('participants', $mutations[0]->field);
        $this->assertEquals('replace', $mutations[0]->operation);
        $this->assertEquals('Marco', $mutations[0]->value);
    }

    public function test_replace_x_with_y_sets_target(): void
    {
        $mutations = $this->interpretStructural('Replace Luca with Marco');

        $this->assertEquals('Luca', $mutations[0]->target);
    }

    public function test_replace_multiword_names_with(): void
    {
        $mutations = $this->interpretStructural('Replace Luca Bianchi with Marco Rossi');

        $this->assertCount(1, $mutations);
        $this->assertEquals('Luca Bianchi', $mutations[0]->target);
        $this->assertEquals('Marco Rossi', $mutations[0]->value);
    }

    // ── Relative temporal shift (Phase 7B) ──────────────────────────────────
    // The interpreter only DETECTS the shift; it never reads proposal state.
    // The concrete time is computed later by the service from the current time.

    public function test_push_it_by_30_minutes_produces_temporal_shift_mutation(): void
    {
        $mutations = $this->interpretStructural('Push it by 30 minutes');

        $this->assertCount(1, $mutations);
        $this->assertSame('time', $mutations[0]->field);
        $this->assertSame('replace', $mutations[0]->operation);
        $this->assertNull($mutations[0]->value);   // value computed later by the service
        $this->assertSame('inferred', $mutations[0]->source);
        $this->assertSame('temporal_shift', $mutations[0]->metadata['contextual_operation']);
        $this->assertSame('minutes', $mutations[0]->metadata['unit']);
        $this->assertSame(30, $mutations[0]->metadata['amount']);
        $this->assertSame('later', $mutations[0]->metadata['direction']);
    }

    public function test_move_it_one_hour_later_converts_hours_to_minutes(): void
    {
        $mutations = $this->interpretStructural('Move it one hour later');

        $this->assertCount(1, $mutations);
        $this->assertSame(60, $mutations[0]->metadata['amount']);
        $this->assertSame('later', $mutations[0]->metadata['direction']);
    }

    public function test_move_it_2_hours_earlier_is_negative_direction(): void
    {
        $mutations = $this->interpretStructural('Move it 2 hours earlier');

        $this->assertCount(1, $mutations);
        $this->assertSame(120, $mutations[0]->metadata['amount']);
        $this->assertSame('earlier', $mutations[0]->metadata['direction']);
    }

    public function test_make_it_30_minutes_earlier_detected(): void
    {
        $mutations = $this->interpretStructural('Make it 30 minutes earlier');

        $this->assertCount(1, $mutations);
        $this->assertSame('temporal_shift', $mutations[0]->metadata['contextual_operation']);
        $this->assertSame(30, $mutations[0]->metadata['amount']);
        $this->assertSame('earlier', $mutations[0]->metadata['direction']);
    }

    public function test_vague_shift_without_quantity_is_not_detected(): void
    {
        // "make it later" / "move it earlier" are intentionally NOT supported:
        // no explicit quantity → no mutation at all.
        $this->assertEmpty($this->interpretStructural('Make it later'));
        $this->assertEmpty($this->interpretStructural('Move it earlier'));
        $this->assertEmpty($this->interpretStructural('Push it back a bit'));
    }

    public function test_move_it_to_friday_is_a_date_replace_not_a_temporal_shift(): void
    {
        // Regression: "Move it to Friday" has no quantity+unit, so it must remain a
        // normal absolute date replace (no contextual temporal_shift metadata).
        $mutations = $this->interpretStructural('Move it to Friday');

        $this->assertCount(1, $mutations);
        $this->assertSame('date', $mutations[0]->field);
        $this->assertArrayNotHasKey('contextual_operation', $mutations[0]->metadata);
    }
}
