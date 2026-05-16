<?php

namespace Tests\Feature\Actions;

use Carbon\Carbon;
use Fluxio\Actions\Interpreters\RuleBasedRefinementInterpreter;
use Tests\TestCase;

/**
 * Verifies that RuleBasedRefinementInterpreter produces the correct
 * NormalizedMutation[] for the refinement inputs that ActionProposalRefinementService
 * is expected to apply. These tests protect the NL → structured-mutation boundary.
 */
class NormalizedRefinementInterpreterTest extends TestCase
{
    private RuleBasedRefinementInterpreter $interpreter;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-05-10 08:00:00');
        $this->interpreter = new RuleBasedRefinementInterpreter();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    // ── Time mutations ────────────────────────────────────────────────────────

    public function test_at_colon_time_produces_single_time_mutation(): void
    {
        $mutations = $this->interpreter->interpret('At 10:30');

        $this->assertCount(1, $mutations);
        $this->assertEquals('time', $mutations[0]->field);
        $this->assertEquals('Time', $mutations[0]->label);
        $this->assertEquals('10:30', $mutations[0]->value);
        $this->assertEquals('detected', $mutations[0]->source);
    }

    public function test_at_dot_time_produces_single_time_mutation(): void
    {
        $mutations = $this->interpreter->interpret('At 10.30');

        $this->assertCount(1, $mutations);
        $this->assertEquals('time', $mutations[0]->field);
        $this->assertEquals('10:30', $mutations[0]->value);
    }

    public function test_at_hour_only_defaults_to_zero_minutes(): void
    {
        $mutations = $this->interpreter->interpret('At 9');

        $this->assertCount(1, $mutations);
        $this->assertEquals('09:00', $mutations[0]->value);
    }

    public function test_at_pm_time_converts_to_24h(): void
    {
        $mutations = $this->interpreter->interpret('At 3pm');

        $this->assertCount(1, $mutations);
        $this->assertEquals('15:00', $mutations[0]->value);
    }

    public function test_at_am_noon_converts_to_midnight(): void
    {
        $mutations = $this->interpreter->interpret('At 12am');

        $this->assertCount(1, $mutations);
        $this->assertEquals('00:00', $mutations[0]->value);
    }

    public function test_morning_keyword_produces_09_00(): void
    {
        $mutations = $this->interpreter->interpret('morning');

        $timeMutations = array_filter($mutations, fn ($m) => $m->field === 'time');
        $this->assertCount(1, $timeMutations);
        $this->assertEquals('09:00', array_values($timeMutations)[0]->value);
    }

    // ── Date mutations ────────────────────────────────────────────────────────

    public function test_tomorrow_produces_single_date_mutation(): void
    {
        $mutations = $this->interpreter->interpret('Tomorrow');

        $this->assertCount(1, $mutations);
        $this->assertEquals('date', $mutations[0]->field);
        $this->assertEquals('Date', $mutations[0]->label);
        $this->assertEquals(now()->addDay()->toDateString(), $mutations[0]->value);
    }

    public function test_friday_keyword_produces_next_friday_date(): void
    {
        $mutations = $this->interpreter->interpret('Friday instead');

        $dateMutations = array_filter($mutations, fn ($m) => $m->field === 'date');
        $this->assertCount(1, $dateMutations);
        $this->assertEquals(Carbon::parse('next friday')->toDateString(), array_values($dateMutations)[0]->value);
    }

    // ── Combined mutations ────────────────────────────────────────────────────

    public function test_tomorrow_morning_produces_date_and_time_mutations(): void
    {
        $mutations = $this->interpreter->interpret('Tomorrow morning');

        $fields = array_map(fn ($m) => $m->field, $mutations);
        $this->assertContains('date', $fields);
        $this->assertContains('time', $fields);
        $this->assertCount(2, $mutations);
    }

    public function test_tomorrow_at_nine_produces_date_and_time_mutations(): void
    {
        $mutations = $this->interpreter->interpret('Tomorrow at 9');

        $fields = array_map(fn ($m) => $m->field, $mutations);
        $this->assertContains('date', $fields);
        $this->assertContains('time', $fields);
    }

    // ── Priority mutations ────────────────────────────────────────────────────

    public function test_high_priority_produces_priority_mutation(): void
    {
        $mutations = $this->interpreter->interpret('High priority');

        $this->assertCount(1, $mutations);
        $this->assertEquals('priority', $mutations[0]->field);
        $this->assertEquals('Priority', $mutations[0]->label);
        $this->assertEquals('high', $mutations[0]->value);
    }

    public function test_urgent_produces_high_priority_mutation(): void
    {
        $mutations = $this->interpreter->interpret('Urgent');

        $this->assertCount(1, $mutations);
        $this->assertEquals('priority', $mutations[0]->field);
        $this->assertEquals('high', $mutations[0]->value);
    }

    public function test_low_priority_produces_low_priority_mutation(): void
    {
        $mutations = $this->interpreter->interpret('Low priority');

        $this->assertCount(1, $mutations);
        $this->assertEquals('priority', $mutations[0]->field);
        $this->assertEquals('low', $mutations[0]->value);
    }

    // ── No-op cases ───────────────────────────────────────────────────────────

    public function test_unrecognized_text_produces_no_mutations(): void
    {
        $mutations = $this->interpreter->interpret('gibberish command xyz');

        $this->assertEmpty($mutations);
    }

    public function test_empty_string_produces_no_mutations(): void
    {
        $mutations = $this->interpreter->interpret('');

        $this->assertEmpty($mutations);
    }

    // ── Mutation DTO structure ────────────────────────────────────────────────

    public function test_mutation_source_defaults_to_detected(): void
    {
        $mutations = $this->interpreter->interpret('At 10:30');

        $this->assertEquals('detected', $mutations[0]->source);
    }

    public function test_mutation_confidence_defaults_to_one(): void
    {
        $mutations = $this->interpreter->interpret('At 10:30');

        $this->assertEquals(1.0, $mutations[0]->confidence);
    }

    // ── Mutation operations ───────────────────────────────────────────────────

    public function test_replace_mutations_carry_replace_operation(): void
    {
        foreach (['At 10:30', 'Tomorrow', 'High priority', 'Urgent', 'Low priority', 'Friday instead'] as $input) {
            $mutations = $this->interpreter->interpret($input);
            foreach ($mutations as $m) {
                $this->assertEquals('replace', $m->operation, "Expected 'replace' for input: {$input}");
            }
        }
    }

    // ── Clear operation ───────────────────────────────────────────────────────

    public function test_remove_priority_phrase_produces_clear_mutation(): void
    {
        $mutations = $this->interpreter->interpret('Remove priority');

        $this->assertCount(1, $mutations);
        $this->assertEquals('priority', $mutations[0]->field);
        $this->assertEquals('clear', $mutations[0]->operation);
        $this->assertNull($mutations[0]->value);
    }

    public function test_clear_priority_phrase_produces_clear_mutation(): void
    {
        $mutations = $this->interpreter->interpret('Clear priority');

        $this->assertCount(1, $mutations);
        $this->assertEquals('priority', $mutations[0]->field);
        $this->assertEquals('clear', $mutations[0]->operation);
    }

    public function test_no_priority_phrase_produces_clear_mutation(): void
    {
        $mutations = $this->interpreter->interpret('No priority');

        $this->assertCount(1, $mutations);
        $this->assertEquals('priority', $mutations[0]->field);
        $this->assertEquals('clear', $mutations[0]->operation);
    }

    public function test_clear_mutation_value_is_null(): void
    {
        $mutations = $this->interpreter->interpret('Remove priority');

        $this->assertNull($mutations[0]->value);
    }

    public function test_clear_mutation_source_is_detected(): void
    {
        $mutations = $this->interpreter->interpret('Remove priority');

        $this->assertEquals('detected', $mutations[0]->source);
    }

    public function test_clear_operation_takes_precedence_over_replace_for_same_field(): void
    {
        // "no priority" must produce clear, not a replace with value "normal" or similar
        $mutations = $this->interpreter->interpret('no priority');

        $this->assertCount(1, $mutations);
        $this->assertEquals('clear', $mutations[0]->operation);
    }

    // ── Proposal-local references ─────────────────────────────────────────────

    public function test_move_it_to_friday_produces_date_mutation(): void
    {
        // "it" is a proposal-local pronoun; date extraction still works via weekday matching
        $mutations = $this->interpreter->interpret('Move it to Friday');

        $dateMutations = array_filter($mutations, fn ($m) => $m->field === 'date');
        $this->assertCount(1, $dateMutations);
        $this->assertEquals(Carbon::parse('next friday')->toDateString(), array_values($dateMutations)[0]->value);
    }

    public function test_move_it_to_friday_operation_is_replace(): void
    {
        $mutations = $this->interpreter->interpret('Move it to Friday');

        $dateMutation = array_values(array_filter($mutations, fn ($m) => $m->field === 'date'))[0];
        $this->assertEquals('replace', $dateMutation->operation);
    }

    // ── Collection append ─────────────────────────────────────────────────────

    public function test_add_too_produces_append_mutation(): void
    {
        $mutations = $this->interpreter->interpret('Add Mario too');

        $this->assertCount(1, $mutations);
        $this->assertEquals('participants', $mutations[0]->field);
        $this->assertEquals('Participants', $mutations[0]->label);
        $this->assertEquals('append', $mutations[0]->operation);
        $this->assertEquals('Mario', $mutations[0]->value);
    }

    public function test_also_add_produces_append_mutation(): void
    {
        $mutations = $this->interpreter->interpret('Also add Marco');

        $this->assertCount(1, $mutations);
        $this->assertEquals('participants', $mutations[0]->field);
        $this->assertEquals('append', $mutations[0]->operation);
        $this->assertEquals('Marco', $mutations[0]->value);
    }

    public function test_append_mutation_target_is_null(): void
    {
        $mutations = $this->interpreter->interpret('Add Mario too');

        $this->assertNull($mutations[0]->target);
    }

    // ── Collection remove ─────────────────────────────────────────────────────

    public function test_remove_name_produces_remove_mutation(): void
    {
        $mutations = $this->interpreter->interpret('Remove Marco');

        $this->assertCount(1, $mutations);
        $this->assertEquals('participants', $mutations[0]->field);
        $this->assertEquals('remove', $mutations[0]->operation);
        $this->assertNull($mutations[0]->value);
    }

    public function test_remove_name_sets_target(): void
    {
        $mutations = $this->interpreter->interpret('Remove Marco');

        $this->assertEquals('Marco', $mutations[0]->target);
    }

    public function test_remove_priority_still_produces_clear_not_remove(): void
    {
        // Regression: "Remove priority" must remain a clear on priority, not remove on participants
        $mutations = $this->interpreter->interpret('Remove priority');

        $this->assertCount(1, $mutations);
        $this->assertEquals('priority', $mutations[0]->field);
        $this->assertEquals('clear', $mutations[0]->operation);
    }

    // ── Collection replace ────────────────────────────────────────────────────

    public function test_replace_x_with_y_produces_replace_mutation(): void
    {
        $mutations = $this->interpreter->interpret('Replace Luca with Marco');

        $this->assertCount(1, $mutations);
        $this->assertEquals('participants', $mutations[0]->field);
        $this->assertEquals('replace', $mutations[0]->operation);
        $this->assertEquals('Marco', $mutations[0]->value);
    }

    public function test_replace_x_with_y_sets_target(): void
    {
        $mutations = $this->interpreter->interpret('Replace Luca with Marco');

        $this->assertEquals('Luca', $mutations[0]->target);
    }

    public function test_replace_multiword_names_with(): void
    {
        $mutations = $this->interpreter->interpret('Replace Luca Bianchi with Marco Rossi');

        $this->assertCount(1, $mutations);
        $this->assertEquals('Luca Bianchi', $mutations[0]->target);
        $this->assertEquals('Marco Rossi', $mutations[0]->value);
    }
}
