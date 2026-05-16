<?php

namespace Tests\Feature\Actions;

use Fluxio\Actions\Interpreters\RuleBasedCommandInterpreter;
use Fluxio\Actions\Resolvers\RuleBasedIntentResolver;
use Tests\TestCase;

/**
 * Verifies that RuleBasedCommandInterpreter produces NormalizedCommands
 * that are structurally correct and equivalent to what the prior direct-resolver
 * path produced, so that switching ActionInterpreterService to the new boundary
 * did not alter observable behavior.
 */
class NormalizedCommandInterpreterTest extends TestCase
{
    private RuleBasedCommandInterpreter $interpreter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->interpreter = $this->app->make(RuleBasedCommandInterpreter::class);
    }

    // ── Intent detection ─────────────────────────────────────────────────────

    public function test_create_task_command_produces_correct_intent(): void
    {
        $command = $this->interpreter->interpret('Create a follow-up task for Rossini tomorrow at 10am');

        $this->assertEquals('create_task', $command->intent);
    }

    public function test_create_task_command_produces_correct_confidence(): void
    {
        $command = $this->interpreter->interpret('Create a follow-up task for Rossini tomorrow at 10am');

        $this->assertEquals(0.9, $command->confidence);
    }

    public function test_schedule_call_command_produces_correct_intent(): void
    {
        $command = $this->interpreter->interpret('Call Rossini');

        $this->assertEquals('schedule_call', $command->intent);
    }

    public function test_schedule_call_command_produces_correct_confidence(): void
    {
        $command = $this->interpreter->interpret('Call Rossini');

        $this->assertEquals(0.7, $command->confidence);
    }

    public function test_unknown_command_produces_unknown_intent(): void
    {
        $command = $this->interpreter->interpret('unknown text xyz');

        $this->assertEquals('unknown', $command->intent);
    }

    public function test_unknown_command_produces_low_confidence(): void
    {
        $command = $this->interpreter->interpret('unknown text xyz');

        $this->assertEquals(0.3, $command->confidence);
    }

    // ── Entity extraction ────────────────────────────────────────────────────

    public function test_rossini_command_extracts_lead_query_entity(): void
    {
        // The interpreter always emits lead_query so entity resolution can decide
        // whether to auto-resolve (single match) or surface an ambiguity panel.
        $command = $this->interpreter->interpret('Call Rossini');

        $this->assertArrayHasKey('lead_query', $command->entities);
        $this->assertEquals('Rossini', $command->entities['lead_query']);
    }

    public function test_rossi_command_extracts_lead_query_entity(): void
    {
        $command = $this->interpreter->interpret('Call Rossi');

        $this->assertArrayHasKey('lead_query', $command->entities);
        $this->assertEquals('Rossi', $command->entities['lead_query']);
    }

    public function test_rossi_command_does_not_extract_resolved_lead(): void
    {
        $command = $this->interpreter->interpret('Call Rossi');

        $this->assertArrayNotHasKey('lead', $command->entities);
    }

    // ── NormalizedCommand metadata ───────────────────────────────────────────

    public function test_command_locale_is_en(): void
    {
        $command = $this->interpreter->interpret('Call Rossini');

        $this->assertEquals('en', $command->locale);
    }

    public function test_source_text_is_preserved_verbatim(): void
    {
        $text    = 'Create a follow-up task for Rossini';
        $command = $this->interpreter->interpret($text);

        $this->assertEquals($text, $command->sourceText);
    }

    public function test_warnings_are_empty_for_recognized_commands(): void
    {
        $command = $this->interpreter->interpret('Call Rossini');

        $this->assertEmpty($command->warnings);
    }
}
