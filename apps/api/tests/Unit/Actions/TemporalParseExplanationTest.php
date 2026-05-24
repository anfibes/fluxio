<?php

namespace Tests\Unit\Actions;

use Carbon\Carbon;
use Fluxio\Actions\Support\DateTimeExpressionParser;
use Fluxio\Actions\Support\TemporalParseResult;
use Tests\TestCase;

/**
 * Phase 6B: verifies the parser-local explainability metadata exposed by
 * DateTimeExpressionParser::explain(). The legacy parse() shape must remain
 * unchanged, and the temporal confidence here is parser-local only — it does
 * not feed the proposal pipeline.
 */
class TemporalParseExplanationTest extends TestCase
{
    private DateTimeExpressionParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-05-10 08:00:00');
        $this->parser = new DateTimeExpressionParser;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    // ── Day-part inferred times ───────────────────────────────────────────────

    public function test_tomorrow_afternoon_explains_relative_date_and_inferred_time(): void
    {
        $r = $this->parser->explain('tomorrow afternoon');

        $this->assertEquals(now()->addDay()->toDateString(), $r->date);
        $this->assertSame('15:00', $r->time);
        $this->assertSame('relative', $r->dateSource);
        $this->assertSame('day_part', $r->timeSource);
        $this->assertSame('tomorrow', $r->dateExpression);
        $this->assertSame('afternoon', $r->timeExpression);
        $this->assertTrue($r->isTimeInferred());
        $this->assertContains("Time inferred from 'afternoon' as 15:00.", $r->explanations);
    }

    public function test_end_of_day_explains_inferred_time_without_date(): void
    {
        $r = $this->parser->explain('end of day');

        $this->assertNull($r->date);
        $this->assertSame('18:00', $r->time);
        $this->assertSame('none', $r->dateSource);
        $this->assertSame('day_part', $r->timeSource);
        $this->assertSame('end of day', $r->timeExpression);
        $this->assertContains("Time inferred from 'end of day' as 18:00.", $r->explanations);
    }

    public function test_morning_explains_inferred_time(): void
    {
        $r = $this->parser->explain('morning');

        $this->assertSame('09:00', $r->time);
        $this->assertSame('day_part', $r->timeSource);
        $this->assertSame('morning', $r->timeExpression);
        $this->assertTrue($r->isTimeInferred());
    }

    public function test_early_morning_explains_specific_phrase(): void
    {
        $r = $this->parser->explain('early morning');

        $this->assertSame('08:00', $r->time);
        $this->assertSame('day_part', $r->timeSource);
        $this->assertSame('early morning', $r->timeExpression);
    }

    public function test_later_today_explains_relative_date_and_inferred_time(): void
    {
        $r = $this->parser->explain('later today');

        $this->assertEquals(now()->toDateString(), $r->date);
        $this->assertSame('17:00', $r->time);
        $this->assertSame('relative', $r->dateSource);
        $this->assertSame('today', $r->dateExpression);
        $this->assertSame('day_part', $r->timeSource);
        $this->assertSame('later today', $r->timeExpression);
    }

    // ── Explicit times ────────────────────────────────────────────────────────

    public function test_at_1030_explains_explicit_time(): void
    {
        $r = $this->parser->explain('at 10:30');

        $this->assertSame('10:30', $r->time);
        $this->assertSame('explicit', $r->timeSource);
        $this->assertSame('at 10:30', $r->timeExpression);
        $this->assertFalse($r->isTimeInferred());
    }

    public function test_explicit_clock_time_wins_over_day_part(): void
    {
        // "morning at 10:30" — explicit clock time must win; no day-part inference.
        $r = $this->parser->explain('morning at 10:30');

        $this->assertSame('10:30', $r->time);
        $this->assertSame('explicit', $r->timeSource);
        $this->assertSame('at 10:30', $r->timeExpression);
        $this->assertFalse($r->isTimeInferred());
        $this->assertNotContains("Time inferred from 'morning' as 09:00.", $r->explanations);
    }

    // ── Weekday resolution ────────────────────────────────────────────────────

    public function test_next_friday_at_3pm_explains_weekday_date_and_explicit_time(): void
    {
        $r = $this->parser->explain('next Friday at 3pm');

        $this->assertEquals(Carbon::parse('next friday')->toDateString(), $r->date);
        $this->assertSame('15:00', $r->time);
        $this->assertSame('weekday', $r->dateSource);
        $this->assertSame('next friday', $r->dateExpression);
        $this->assertSame('explicit', $r->timeSource);
        $this->assertSame('at 3pm', $r->timeExpression);
    }

    public function test_this_friday_explains_weekday_date(): void
    {
        $r = $this->parser->explain('this Friday');

        $todayIso = now()->dayOfWeekIso;
        $diff = (5 - $todayIso + 7) % 7;
        $expected = now()->addDays($diff)->toDateString();

        $this->assertEquals($expected, $r->date);
        $this->assertSame('weekday', $r->dateSource);
        $this->assertSame('this friday', $r->dateExpression);
    }

    // ── Relative dates ────────────────────────────────────────────────────────

    public function test_day_after_tomorrow_explains_relative_date(): void
    {
        $r = $this->parser->explain('day after tomorrow');

        $this->assertEquals(now()->addDays(2)->toDateString(), $r->date);
        $this->assertSame('relative', $r->dateSource);
        $this->assertSame('day after tomorrow', $r->dateExpression);
        $this->assertSame('none', $r->timeSource);
    }

    public function test_in_two_days_explains_relative_date(): void
    {
        $r = $this->parser->explain('in two days');

        $this->assertEquals(now()->addDays(2)->toDateString(), $r->date);
        $this->assertSame('relative', $r->dateSource);
        $this->assertSame('in two days', $r->dateExpression);
    }

    // ── Confidence ────────────────────────────────────────────────────────────

    public function test_confidence_is_within_unit_interval(): void
    {
        foreach (['tomorrow afternoon', 'end of day', 'next Friday at 3pm', 'at 10:30', 'morning', 'this Friday', 'gibberish xyz'] as $text) {
            $c = $this->parser->explain($text)->confidence;
            $this->assertGreaterThanOrEqual(0.0, $c, "confidence < 0 for: {$text}");
            $this->assertLessThanOrEqual(1.0, $c, "confidence > 1 for: {$text}");
        }
    }

    public function test_explicit_time_has_higher_confidence_than_inferred_time(): void
    {
        $explicit = $this->parser->explain('at 10:30')->confidence;   // 1.0
        $inferred = $this->parser->explain('morning')->confidence;    // 0.75

        $this->assertGreaterThan($inferred, $explicit);
    }

    public function test_no_temporal_expression_yields_none_sources_and_zero_confidence(): void
    {
        $r = $this->parser->explain('high priority');

        $this->assertNull($r->date);
        $this->assertNull($r->time);
        $this->assertSame('none', $r->dateSource);
        $this->assertSame('none', $r->timeSource);
        $this->assertSame(0.0, $r->confidence);
        $this->assertSame([], $r->explanations);
        $this->assertInstanceOf(TemporalParseResult::class, $r);
    }

    // ── Legacy compatibility ──────────────────────────────────────────────────

    public function test_legacy_parse_shape_is_unchanged(): void
    {
        $this->assertSame(
            ['date' => now()->addDay()->toDateString(), 'time' => '15:00'],
            $this->parser->parse('tomorrow afternoon'),
        );
        $this->assertSame(['time' => '10:30'], $this->parser->parse('at 10:30'));
        $this->assertSame([], $this->parser->parse('high priority'));
    }
}
