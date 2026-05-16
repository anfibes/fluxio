<?php

namespace Tests\Unit\Actions;

use Carbon\Carbon;
use Fluxio\Actions\Support\DateTimeExpressionParser;
use Tests\TestCase;

/**
 * Verifies that DateTimeExpressionParser produces consistent, normalized
 * temporal values for all expressions supported by both the command and
 * refinement interpretation paths.
 */
class DateTimeExpressionParserTest extends TestCase
{
    private DateTimeExpressionParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-05-10 08:00:00');
        $this->parser = new DateTimeExpressionParser();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    // ── Date expressions ─────────────────────────────────────────────────────

    public function test_tomorrow_returns_next_day(): void
    {
        $result = $this->parser->parse('Tomorrow');
        $this->assertEquals(now()->addDay()->toDateString(), $result['date']);
    }

    public function test_tomorrow_is_case_insensitive(): void
    {
        $result = $this->parser->parse('tomorrow morning');
        $this->assertArrayHasKey('date', $result);
    }

    public function test_friday_returns_next_friday(): void
    {
        $result = $this->parser->parse('Friday instead');
        $this->assertEquals(Carbon::parse('next friday')->toDateString(), $result['date']);
    }

    public function test_monday_returns_next_monday(): void
    {
        $result = $this->parser->parse('Monday');
        $this->assertEquals(Carbon::parse('next monday')->toDateString(), $result['date']);
    }

    public function test_all_weekday_names_are_recognized(): void
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        foreach ($days as $day) {
            $result = $this->parser->parse($day);
            $this->assertArrayHasKey('date', $result, "Expected date for day: {$day}");
        }
    }

    public function test_no_date_expression_omits_date_key(): void
    {
        $result = $this->parser->parse('At 10:30');
        $this->assertArrayNotHasKey('date', $result);
    }

    // ── Time expressions ─────────────────────────────────────────────────────

    public function test_morning_returns_0900(): void
    {
        $result = $this->parser->parse('morning');
        $this->assertEquals('09:00', $result['time']);
    }

    public function test_at_colon_time_returns_normalized_time(): void
    {
        $result = $this->parser->parse('At 10:30');
        $this->assertEquals('10:30', $result['time']);
    }

    public function test_at_dot_time_returns_normalized_time(): void
    {
        $result = $this->parser->parse('At 10.30');
        $this->assertEquals('10:30', $result['time']);
    }

    public function test_at_hour_only_defaults_minutes_to_zero(): void
    {
        $result = $this->parser->parse('At 9');
        $this->assertEquals('09:00', $result['time']);
    }

    public function test_at_pm_converts_to_24h(): void
    {
        $result = $this->parser->parse('At 4pm');
        $this->assertEquals('16:00', $result['time']);
    }

    public function test_at_3pm_converts_to_1500(): void
    {
        $result = $this->parser->parse('At 3pm');
        $this->assertEquals('15:00', $result['time']);
    }

    public function test_at_noon_am_converts_to_midnight(): void
    {
        $result = $this->parser->parse('At 12am');
        $this->assertEquals('00:00', $result['time']);
    }

    public function test_at_noon_pm_remains_1200(): void
    {
        $result = $this->parser->parse('At 12pm');
        $this->assertEquals('12:00', $result['time']);
    }

    public function test_no_time_expression_omits_time_key(): void
    {
        $result = $this->parser->parse('Tomorrow');
        $this->assertArrayNotHasKey('time', $result);
    }

    // ── Combined expressions ─────────────────────────────────────────────────

    public function test_tomorrow_morning_returns_date_and_time(): void
    {
        $result = $this->parser->parse('Tomorrow morning');
        $this->assertEquals(now()->addDay()->toDateString(), $result['date']);
        $this->assertEquals('09:00', $result['time']);
    }

    public function test_friday_at_4pm_returns_date_and_time(): void
    {
        $result = $this->parser->parse('Friday at 4pm');
        $this->assertEquals(Carbon::parse('next friday')->toDateString(), $result['date']);
        $this->assertEquals('16:00', $result['time']);
    }

    public function test_tomorrow_at_colon_time_returns_both(): void
    {
        $result = $this->parser->parse('Tomorrow at 10:30');
        $this->assertEquals(now()->addDay()->toDateString(), $result['date']);
        $this->assertEquals('10:30', $result['time']);
    }

    // ── No-expression cases ──────────────────────────────────────────────────

    public function test_unrecognized_text_returns_empty_array(): void
    {
        $result = $this->parser->parse('gibberish xyz');
        $this->assertEmpty($result);
    }

    public function test_empty_string_returns_empty_array(): void
    {
        $result = $this->parser->parse('');
        $this->assertEmpty($result);
    }

    public function test_priority_text_returns_empty_array(): void
    {
        $result = $this->parser->parse('High priority');
        $this->assertEmpty($result);
    }
}
