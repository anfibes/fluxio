<?php

namespace Tests\Unit\Actions;

use Carbon\Carbon;
use Fluxio\Actions\Services\ActionInterpreterService;
use Tests\TestCase;

/**
 * Phase 6D: scheduling intents (schedule_call / schedule_meeting) emit an
 * informational warning when exactly one of date/time was extracted and the
 * other is missing. The warning never changes status, readiness, confidence,
 * entities, ambiguities, or execution. Entity resolution is in-memory; no DB.
 */
class PartialTemporalWarningTest extends TestCase
{
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

    private function partialWarnings(array $warnings): array
    {
        return array_values(array_filter($warnings, fn (string $w) => str_contains($w, 'still missing')));
    }

    public function test_schedule_call_with_date_only_warns_time_missing(): void
    {
        $p = $this->service->interpret('Schedule a call with Rossini tomorrow');

        $this->assertSame('schedule_call', $p->intent);
        $this->assertEquals(now()->addDay()->toDateString(), $p->entities['date']);
        $this->assertArrayNotHasKey('time', $p->entities);

        $this->assertContains('time', $this->missingKeys($p->missing));
        $this->assertContains("Date resolved from 'tomorrow', but time is still missing.", $p->warnings);
        $this->assertSame('draft', $p->status);
    }

    public function test_schedule_meeting_with_date_only_warns_time_missing(): void
    {
        $p = $this->service->interpret('Schedule a meeting with Rossini next Friday');

        $this->assertSame('schedule_meeting', $p->intent);
        $this->assertArrayHasKey('date', $p->entities);
        $this->assertArrayNotHasKey('time', $p->entities);

        $this->assertContains('time', $this->missingKeys($p->missing));
        $this->assertContains("Date resolved from 'next friday', but time is still missing.", $p->warnings);
        $this->assertSame('draft', $p->status);
    }

    public function test_schedule_call_with_time_only_warns_date_missing(): void
    {
        $p = $this->service->interpret('Schedule a call with Rossini at 3pm');

        $this->assertSame('15:00', $p->entities['time']);
        $this->assertArrayNotHasKey('date', $p->entities);

        $this->assertContains('date', $this->missingKeys($p->missing));
        // Uses the explicit timeExpression in the warning (test #8).
        $this->assertContains("Time resolved from 'at 3pm', but date is still missing.", $p->warnings);
        $this->assertSame('draft', $p->status);
    }

    public function test_schedule_call_with_both_date_and_inferred_time_has_no_partial_warning(): void
    {
        $p = $this->service->interpret('Schedule a call with Rossini tomorrow afternoon');

        // Both present.
        $this->assertArrayHasKey('date', $p->entities);
        $this->assertSame('15:00', $p->entities['time']);

        // Inferred-time warning remains; partial temporal warning is absent.
        $this->assertContains("Time inferred from 'afternoon' as 15:00.", $p->warnings);
        $this->assertSame([], $this->partialWarnings($p->warnings));
    }

    public function test_schedule_call_with_neither_date_nor_time_has_no_partial_warning(): void
    {
        $p = $this->service->interpret('Schedule a call with Rossini');

        $this->assertArrayNotHasKey('date', $p->entities);
        $this->assertArrayNotHasKey('time', $p->entities);

        $this->assertContains('date', $this->missingKeys($p->missing));
        $this->assertContains('time', $this->missingKeys($p->missing));
        $this->assertSame([], $this->partialWarnings($p->warnings));
        $this->assertSame('draft', $p->status);
    }

    public function test_create_task_with_date_only_has_no_partial_scheduling_warning(): void
    {
        $p = $this->service->interpret('Create a task for Rossini tomorrow');

        $this->assertSame('create_task', $p->intent);
        $this->assertArrayHasKey('date', $p->entities);
        $this->assertSame([], $this->partialWarnings($p->warnings));
    }

    public function test_partial_warning_is_not_duplicated(): void
    {
        $p = $this->service->interpret('Schedule a call with Rossini tomorrow');

        $this->assertCount(1, $this->partialWarnings($p->warnings));
    }
}
