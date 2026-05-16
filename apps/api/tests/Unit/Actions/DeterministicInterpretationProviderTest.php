<?php

namespace Tests\Unit\Actions;

use Carbon\Carbon;
use Fluxio\Actions\Interpretation\Contracts\InterpretationProviderInterface;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;
use Fluxio\Actions\Interpretation\Providers\DeterministicInterpretationProvider;
use Tests\TestCase;

class DeterministicInterpretationProviderTest extends TestCase
{
    private DeterministicInterpretationProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-05-10 08:00:00');
        $this->provider = $this->app->make(DeterministicInterpretationProvider::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function context(string $locale = 'en'): InterpretationContext
    {
        return new InterpretationContext(locale: $locale);
    }

    // ── Intent detection ─────────────────────────────────────────────────────

    public function test_schedule_meeting_with_rossi_produces_schedule_meeting_intent(): void
    {
        $command = $this->provider->interpret('Schedule a meeting with Rossi tomorrow morning', $this->context());

        $this->assertEquals('schedule_meeting', $command->intent);
    }

    public function test_schedule_meeting_with_rossini_produces_schedule_meeting_intent(): void
    {
        $command = $this->provider->interpret('Schedule a meeting with Rossini tomorrow at 4pm', $this->context());

        $this->assertEquals('schedule_meeting', $command->intent);
    }

    public function test_create_task_produces_create_task_intent(): void
    {
        $command = $this->provider->interpret('Create a task', $this->context());

        $this->assertEquals('create_task', $command->intent);
    }

    public function test_unknown_text_produces_unknown_intent(): void
    {
        $command = $this->provider->interpret('Show me the dashboard', $this->context());

        $this->assertEquals('unknown', $command->intent);
    }

    // ── Confidence ───────────────────────────────────────────────────────────

    public function test_schedule_meeting_confidence_matches_registry(): void
    {
        $command = $this->provider->interpret('Schedule a meeting with Rossini tomorrow at 4pm', $this->context());

        $this->assertEquals(0.7, $command->confidence);
    }

    public function test_create_task_confidence_matches_registry(): void
    {
        $command = $this->provider->interpret('Create a task', $this->context());

        $this->assertEquals(0.9, $command->confidence);
    }

    public function test_unknown_intent_has_fallback_confidence(): void
    {
        $command = $this->provider->interpret('Show me the dashboard', $this->context());

        $this->assertEquals(0.3, $command->confidence);
    }

    // ── Entity extraction ────────────────────────────────────────────────────

    public function test_rossi_produces_lead_query_entity(): void
    {
        $command = $this->provider->interpret('Schedule a meeting with Rossi tomorrow morning', $this->context());

        $this->assertArrayHasKey('lead_query', $command->entities);
        $this->assertEquals('Rossi', $command->entities['lead_query']);
    }

    public function test_rossini_produces_lead_query_entity(): void
    {
        $command = $this->provider->interpret('Schedule a meeting with Rossini tomorrow at 4pm', $this->context());

        $this->assertArrayHasKey('lead_query', $command->entities);
        $this->assertEquals('Rossini', $command->entities['lead_query']);
    }

    public function test_tomorrow_morning_produces_date_and_time_entities(): void
    {
        $command = $this->provider->interpret('Schedule a meeting with Rossi tomorrow morning', $this->context());

        $this->assertArrayHasKey('date', $command->entities);
        $this->assertArrayHasKey('time', $command->entities);
        $this->assertEquals(Carbon::now()->addDay()->toDateString(), $command->entities['date']);
        $this->assertEquals('09:00', $command->entities['time']);
    }

    public function test_at_4pm_produces_time_entity(): void
    {
        $command = $this->provider->interpret('Schedule a meeting with Rossini tomorrow at 4pm', $this->context());

        $this->assertEquals('16:00', $command->entities['time']);
    }

    // ── NormalizedCommand metadata ───────────────────────────────────────────

    public function test_source_text_is_preserved_verbatim(): void
    {
        $text    = 'Schedule a meeting with Rossini tomorrow at 4pm';
        $command = $this->provider->interpret($text, $this->context());

        $this->assertEquals($text, $command->sourceText);
    }

    public function test_locale_comes_from_context(): void
    {
        $command = $this->provider->interpret('Create a task', $this->context('it'));

        $this->assertEquals('it', $command->locale);
    }

    public function test_default_locale_is_en(): void
    {
        $command = $this->provider->interpret('Create a task', $this->context());

        $this->assertEquals('en', $command->locale);
    }

    public function test_warnings_are_empty(): void
    {
        $command = $this->provider->interpret('Create a task', $this->context());

        $this->assertEmpty($command->warnings);
    }

    // ── Equivalence with RuleBasedCommandInterpreter ─────────────────────────

    public function test_produces_same_output_as_rule_based_command_interpreter(): void
    {
        $interpreter = $this->app->make(\Fluxio\Actions\Interpreters\RuleBasedCommandInterpreter::class);

        $phrases = [
            'Schedule a meeting with Rossi tomorrow morning',
            'Schedule a meeting with Rossini tomorrow at 4pm',
            'Create a task',
            'Call Rossini',
            'Assign Rossini to Marco',
        ];

        foreach ($phrases as $phrase) {
            $fromInterpreter = $interpreter->interpret($phrase);
            $fromProvider    = $this->provider->interpret($phrase, $this->context('en'));

            $this->assertEquals($fromInterpreter->intent,     $fromProvider->intent,     "Intent mismatch for: {$phrase}");
            $this->assertEquals($fromInterpreter->confidence, $fromProvider->confidence, "Confidence mismatch for: {$phrase}");
            $this->assertEquals($fromInterpreter->entities,   $fromProvider->entities,   "Entities mismatch for: {$phrase}");
            $this->assertEquals($fromInterpreter->sourceText, $fromProvider->sourceText, "SourceText mismatch for: {$phrase}");
        }
    }
}
