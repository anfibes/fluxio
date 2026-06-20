<?php

namespace Tests\Unit\Actions;

use Carbon\Carbon;
use Fluxio\Actions\Services\ActionInterpreterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoLeads;
use Tests\TestCase;

/**
 * Phase 6C.1: schedule_call must propagate temporal entities already extracted
 * by the deterministic parser, so the proposal never marks Date/Time as missing
 * while also surfacing an inferred-time warning. Lead ambiguity can still keep
 * the proposal in draft. Entity resolution is in-memory; no DB needed.
 */
class ScheduleCallTemporalPropagationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoLeads;

    private ActionInterpreterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-05-10 08:00:00');
        $this->service = $this->app->make(ActionInterpreterService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function missingKeys(array $missing): array
    {
        return array_map(fn ($f) => $f->key, $missing);
    }

    private function field(array $editableFields, string $key): ?object
    {
        foreach ($editableFields as $f) {
            if ($f->key === $key) {
                return $f;
            }
        }

        return null;
    }

    public function test_schedule_call_with_day_part_time_does_not_mark_date_or_time_missing(): void
    {
        $proposal = $this->service->interpret('Schedule a call with Rossini tomorrow afternoon');

        $this->assertSame('schedule_call', $proposal->intent);

        // Temporal entities are present.
        $this->assertEquals(now()->addDay()->toDateString(), $proposal->entities['date']);
        $this->assertSame('15:00', $proposal->entities['time']);

        // Neither date nor time is marked missing.
        $this->assertNotContains('date', $this->missingKeys($proposal->missing));
        $this->assertNotContains('time', $this->missingKeys($proposal->missing));

        // Editable fields show resolved values (not "missing").
        $dateField = $this->field($proposal->editable_fields, 'date');
        $timeField = $this->field($proposal->editable_fields, 'time');
        $this->assertSame('detected', $dateField->source);
        $this->assertEquals(now()->addDay()->toDateString(), $dateField->value);
        $this->assertSame('detected', $timeField->source);
        $this->assertSame('15:00', $timeField->value);

        // Inferred-time warning is still surfaced (consistent, not contradictory).
        $this->assertContains("Time inferred from 'afternoon' as 15:00.", $proposal->warnings);

        // Lead (Rossini) resolves, date+time present, no blocking ambiguity → ready.
        $this->assertSame('ready', $proposal->status);
    }

    public function test_schedule_call_with_explicit_time_does_not_emit_inferred_time_warning(): void
    {
        $proposal = $this->service->interpret('Schedule a call with Rossini tomorrow at 3pm');

        $this->assertSame('15:00', $proposal->entities['time']);
        $this->assertSame([], $proposal->warnings);
        $this->assertNotContains('date', $this->missingKeys($proposal->missing));
        $this->assertNotContains('time', $this->missingKeys($proposal->missing));
        $this->assertSame('ready', $proposal->status);
    }

    public function test_schedule_call_with_lead_ambiguity_remains_draft_but_date_time_are_present(): void
    {
        // "Rossi" is ambiguous (multiple matches) → blocking ambiguity.
        $proposal = $this->service->interpret('Schedule a call with Rossi tomorrow afternoon');

        // Date/time are still extracted and not missing.
        $this->assertEquals(now()->addDay()->toDateString(), $proposal->entities['date']);
        $this->assertSame('15:00', $proposal->entities['time']);
        $this->assertNotContains('date', $this->missingKeys($proposal->missing));
        $this->assertNotContains('time', $this->missingKeys($proposal->missing));

        // Blocking lead ambiguity keeps the proposal in draft.
        $this->assertSame('draft', $proposal->status);
        $this->assertTrue(collect($proposal->ambiguities)->contains(fn ($a) => ($a['key'] ?? null) === 'lead' && ($a['blocking'] ?? false)));
    }

    public function test_schedule_call_without_time_still_marks_time_missing(): void
    {
        $proposal = $this->service->interpret('Schedule a call with Rossini tomorrow');

        $this->assertEquals(now()->addDay()->toDateString(), $proposal->entities['date']);
        $this->assertArrayNotHasKey('time', $proposal->entities);

        $this->assertNotContains('date', $this->missingKeys($proposal->missing));
        $this->assertContains('time', $this->missingKeys($proposal->missing));
        $this->assertSame('draft', $proposal->status);
    }

    public function test_schedule_call_without_date_still_marks_date_missing(): void
    {
        $proposal = $this->service->interpret('Schedule a call with Rossini at 3pm');

        $this->assertSame('15:00', $proposal->entities['time']);
        $this->assertArrayNotHasKey('date', $proposal->entities);

        $this->assertContains('date', $this->missingKeys($proposal->missing));
        $this->assertNotContains('time', $this->missingKeys($proposal->missing));
        $this->assertSame('draft', $proposal->status);
    }

    public function test_schedule_call_without_temporal_marks_both_missing_and_stays_draft(): void
    {
        // Regression: original behaviour for a bare schedule_call is preserved.
        $proposal = $this->service->interpret('Schedule a call with Rossini');

        $this->assertSame('draft', $proposal->status);
        $this->assertCount(2, $proposal->missing);
        $this->assertContains('date', $this->missingKeys($proposal->missing));
        $this->assertContains('time', $this->missingKeys($proposal->missing));
        $this->assertSame([], $proposal->warnings);
    }
}
