<?php

namespace Tests\Unit\Actions;

use Carbon\Carbon;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;
use Fluxio\Actions\Interpretation\Providers\DeterministicInterpretationProvider;
use Fluxio\Actions\Resolvers\RuleBasedIntentResolver;
use Fluxio\Actions\Services\ActionInterpreterService;
use Fluxio\Actions\Support\DateTimeExpressionParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoLeads;
use Tests\TestCase;

/**
 * Phase 6C: verifies that a time inferred from a day-part expression surfaces a
 * single informational warning through the deterministic interpretation flow
 * (resolver → NormalizedCommand → proposal). Explicit clock times and weekday
 * date resolution must NOT generate warnings, and the warning must not change
 * proposal status / readiness / confidence.
 */
class TemporalWarningIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoLeads;

    private DeterministicInterpretationProvider $provider;

    private ActionInterpreterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-05-10 08:00:00');
        $this->provider = $this->app->make(DeterministicInterpretationProvider::class);
        $this->service = $this->app->make(ActionInterpreterService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function warnings(string $text): array
    {
        return $this->provider->interpret($text, new InterpretationContext)->warnings;
    }

    // ── Inferred day-part times → warning present ─────────────────────────────

    public function test_tomorrow_afternoon_emits_inferred_time_warning(): void
    {
        $this->assertContains(
            "Time inferred from 'afternoon' as 15:00.",
            $this->warnings('Schedule a meeting with Rossini tomorrow afternoon'),
        );
    }

    public function test_end_of_day_emits_inferred_time_warning(): void
    {
        $this->assertContains(
            "Time inferred from 'end of day' as 18:00.",
            $this->warnings('Call Rossi end of day'),
        );
    }

    public function test_morning_emits_inferred_time_warning(): void
    {
        $this->assertContains(
            "Time inferred from 'morning' as 09:00.",
            $this->warnings('Schedule a call in the morning'),
        );
    }

    public function test_later_today_emits_inferred_time_warning(): void
    {
        $this->assertContains(
            "Time inferred from 'later today' as 17:00.",
            $this->warnings('Call Rossi later today'),
        );
    }

    // ── Explicit / weekday → no warning ───────────────────────────────────────

    public function test_explicit_clock_time_emits_no_inferred_time_warning(): void
    {
        // Explicit time → no day-part inference warning. (A Phase 6D partial
        // "date still missing" warning is expected here and asserted separately.)
        $warnings = $this->warnings('Schedule a meeting with Rossini at 10:30');

        $this->assertEmpty(array_filter($warnings, fn (string $w) => str_contains($w, 'inferred from')));
    }

    public function test_next_friday_at_3pm_emits_no_warning(): void
    {
        $this->assertSame([], $this->warnings('Schedule a meeting with Rossini next Friday at 3pm'));
    }

    public function test_weekday_date_resolution_alone_emits_no_warning(): void
    {
        // 'this Friday' resolves a date (weekday) but infers no day-part time.
        $this->assertSame([], $this->warnings('Schedule a meeting with Rossini this Friday at 9am'));
    }

    public function test_only_one_warning_when_date_and_time_both_inferred(): void
    {
        // 'tomorrow afternoon' → relative date + inferred day-part time.
        // Only the time-inference warning is emitted.
        $warnings = $this->warnings('Schedule a meeting with Rossini tomorrow afternoon');

        $this->assertCount(1, $warnings);
        $this->assertSame("Time inferred from 'afternoon' as 15:00.", $warnings[0]);
    }

    // ── Proposal surfacing + readiness unchanged ──────────────────────────────

    public function test_warning_surfaces_on_proposal_without_changing_readiness(): void
    {
        $proposal = $this->service->interpret('Schedule a meeting with Rossini tomorrow afternoon');

        // Warning is surfaced informationally.
        $this->assertContains("Time inferred from 'afternoon' as 15:00.", $proposal->warnings);

        // Readiness/status computed normally: lead resolved + date + time present → ready.
        $this->assertSame('schedule_meeting', $proposal->intent);
        $this->assertSame('ready', $proposal->status);
        $this->assertSame('15:00', $proposal->entities['time']);
    }

    public function test_explicit_time_proposal_has_no_warnings(): void
    {
        $proposal = $this->service->interpret('Schedule a meeting with Rossini next Friday at 3pm');

        $this->assertSame([], $proposal->warnings);
        $this->assertSame('ready', $proposal->status);
    }

    // ── Legacy parse() compatibility ──────────────────────────────────────────

    public function test_parse_shape_is_unchanged(): void
    {
        $parser = new DateTimeExpressionParser;

        $this->assertSame(
            ['date' => now()->addDay()->toDateString(), 'time' => '15:00'],
            $parser->parse('tomorrow afternoon'),
        );
        $this->assertSame(['time' => '10:30'], $parser->parse('at 10:30'));
    }

    // ── Entities / resolution unaffected ──────────────────────────────────────

    public function test_entities_and_resolution_unaffected_by_warning(): void
    {
        $resolver = $this->app->make(RuleBasedIntentResolver::class);
        $parsed = $resolver->resolve('Schedule a meeting with Rossini tomorrow afternoon');

        // Same entities as before: lead_query + date + time. Warning lives separately.
        $this->assertSame('Rossini', $parsed->entities['lead_query']);
        $this->assertEquals(now()->addDay()->toDateString(), $parsed->entities['date']);
        $this->assertSame('15:00', $parsed->entities['time']);
        $this->assertArrayNotHasKey('lead', $parsed->entities); // resolution happens later, not in resolver
    }
}
