<?php

namespace Tests\Unit\Actions;

use Carbon\Carbon;
use Fluxio\Actions\Support\DateTimeExpressionParser;
use Tests\TestCase;

/**
 * Phase 6C.1: edge-case hardening for DateTimeExpressionParser. These are mostly
 * regression tests pinning the resolution precedence (explicit > day-part,
 * most-specific day-part first) and weekday qualifier semantics. Time is frozen
 * so weekday-relative cases are deterministic.
 */
class DateTimeExpressionParserEdgeCaseTest extends TestCase
{
    private DateTimeExpressionParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        // 2026-05-15 is a Friday — used for the weekday-on-its-own-day cases.
        Carbon::setTestNow('2026-05-15 08:00:00');
        $this->parser = new DateTimeExpressionParser;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    public function test_explicit_time_wins_over_day_part(): void
    {
        $result = $this->parser->parse('tomorrow afternoon at 10:30');
        $this->assertSame('10:30', $result['time']);

        $explained = $this->parser->explain('tomorrow afternoon at 10:30');
        $this->assertSame('explicit', $explained->timeSource);
        $this->assertFalse($explained->isTimeInferred());
    }

    public function test_early_morning_wins_over_morning(): void
    {
        $this->assertSame('08:00', $this->parser->parse('tomorrow early morning')['time']);
        $this->assertSame('early morning', $this->parser->explain('tomorrow early morning')->timeExpression);
    }

    public function test_end_of_day_resolves_to_1800(): void
    {
        $result = $this->parser->parse('end of day');
        $this->assertSame('18:00', $result['time']);
        $this->assertArrayNotHasKey('date', $result);
    }

    public function test_tonight_resolves_time_without_date(): void
    {
        $result = $this->parser->parse('tonight');
        $this->assertSame('20:00', $result['time']);
        $this->assertArrayNotHasKey('date', $result);
    }

    public function test_later_today_resolves_both_date_and_time(): void
    {
        $result = $this->parser->parse('later today');
        $this->assertSame(now()->toDateString(), $result['date']);
        $this->assertSame('17:00', $result['time']);
    }

    public function test_this_friday_on_friday_resolves_to_today(): void
    {
        // Frozen now is a Friday.
        $this->assertSame(now()->toDateString(), $this->parser->parse('this Friday')['date']);
    }

    public function test_next_friday_on_friday_resolves_to_next_week(): void
    {
        // Frozen now is a Friday → next Friday is 7 days later.
        $this->assertSame(now()->addWeek()->toDateString(), $this->parser->parse('next Friday')['date']);
    }

    public function test_bare_friday_preserves_existing_next_occurrence_behavior(): void
    {
        $this->assertSame(
            Carbon::parse('next friday')->toDateString(),
            $this->parser->parse('Friday')['date'],
        );
    }

    public function test_in_2_days_and_in_two_days_resolve_to_the_same_date(): void
    {
        $this->assertSame(
            $this->parser->parse('in 2 days')['date'],
            $this->parser->parse('in two days')['date'],
        );
        $this->assertSame(now()->addDays(2)->toDateString(), $this->parser->parse('in 2 days')['date']);
    }

    public function test_explicit_at_3pm_normalizes_to_1500(): void
    {
        $this->assertSame('15:00', $this->parser->parse('at 3pm')['time']);
    }

    public function test_explicit_at_12am_normalizes_to_0000(): void
    {
        $this->assertSame('00:00', $this->parser->parse('at 12am')['time']);
    }

    public function test_explicit_at_12pm_normalizes_to_1200(): void
    {
        $this->assertSame('12:00', $this->parser->parse('at 12pm')['time']);
    }

    public function test_explicit_at_9_normalizes_to_0900(): void
    {
        $this->assertSame('09:00', $this->parser->parse('at 9')['time']);
    }

    public function test_unsupported_phrase_returns_empty_result(): void
    {
        $this->assertSame([], $this->parser->parse('please do the needful'));
    }

    public function test_explain_confidence_always_within_unit_interval(): void
    {
        $phrases = [
            'tomorrow afternoon at 10:30', 'tomorrow early morning', 'end of day',
            'tonight', 'later today', 'this Friday', 'next Friday', 'Friday',
            'in 2 days', 'in two days', 'at 3pm', 'at 12am', 'at 12pm', 'at 9',
            'please do the needful', '',
        ];

        foreach ($phrases as $phrase) {
            $c = $this->parser->explain($phrase)->confidence;
            $this->assertGreaterThanOrEqual(0.0, $c, "confidence < 0 for: {$phrase}");
            $this->assertLessThanOrEqual(1.0, $c, "confidence > 1 for: {$phrase}");
        }
    }
}
