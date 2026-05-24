<?php

namespace Fluxio\Actions\Support;

use Carbon\Carbon;

class DateTimeExpressionParser
{
    /** WEEKDAYS index 0=Monday … 6=Sunday (ISO weekday = index + 1). */
    private const WEEKDAYS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    /**
     * Fixed day-part → time mappings. Deterministic and intentionally explicit
     * so they are easy to adjust later. Ordered most-specific first so that
     * "early morning" is matched before "morning".
     *
     * @var array<string, string>
     */
    private const DAY_PARTS = [
        'early morning' => '08:00',
        'later today' => '17:00',
        'end of day' => '18:00',
        'morning' => '09:00',
        'afternoon' => '15:00',
        'evening' => '18:00',
        'tonight' => '20:00',
    ];

    /** @var array<string, int> */
    private const NUMBER_WORDS = [
        'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4,
        'five' => 5, 'six' => 6, 'seven' => 7,
    ];

    /**
     * Parse temporal expressions from free text.
     *
     * Returns a sparse array containing only the keys that were recognised.
     * Callers decide how to use the values (entities, mutations, …).
     *
     * @return array{date?: string, time?: string}
     */
    public function parse(string $text): array
    {
        $result = [];

        $date = $this->parseDate($text);
        if ($date !== null) {
            $result['date'] = $date;
        }

        $time = $this->parseTime($text);
        if ($time !== null) {
            $result['time'] = $time;
        }

        return $result;
    }

    // ── Date ─────────────────────────────────────────────────────────────────

    private function parseDate(string $text): ?string
    {
        $lower = mb_strtolower(trim($text));

        // Order matters: more specific phrases first.
        if (str_contains($lower, 'day after tomorrow')) {
            return now()->addDays(2)->toDateString();
        }

        if (str_contains($lower, 'tomorrow')) {
            return now()->addDay()->toDateString();
        }

        // "in two days" / "in 2 days"
        if ((bool) preg_match('/\bin\s+(\d+|one|two|three|four|five|six|seven)\s+days?\b/i', $lower, $m)) {
            return now()->addDays($this->wordToNumber($m[1]))->toDateString();
        }

        // "today" (also covers "later today"); guarded so it never matches inside other words.
        if ((bool) preg_match('/\btoday\b/i', $lower)) {
            return now()->toDateString();
        }

        // Weekday, with optional "next"/"this" qualifier.
        foreach (self::WEEKDAYS as $index => $day) {
            if ((bool) preg_match('/\b(next|this)?\s*'.$day.'\b/i', $lower, $m)) {
                return $this->resolveWeekday($index, mb_strtolower(trim($m[1] ?? '')));
            }
        }

        return null;
    }

    /**
     * Resolve a weekday to a concrete date.
     *
     * - "this <day>" → the upcoming occurrence including today.
     * - bare / "next <day>" → the next occurrence strictly after today
     *   (preserves the pre-existing behaviour for a bare weekday).
     */
    private function resolveWeekday(int $weekdayIndex, string $qualifier): string
    {
        if ($qualifier === 'this') {
            $targetIso = $weekdayIndex + 1;          // 1=Monday … 7=Sunday
            $todayIso = now()->dayOfWeekIso;
            $diff = ($targetIso - $todayIso + 7) % 7;

            return now()->addDays($diff)->toDateString();
        }

        return Carbon::parse('next '.self::WEEKDAYS[$weekdayIndex])->toDateString();
    }

    private function wordToNumber(string $word): int
    {
        if (is_numeric($word)) {
            return (int) $word;
        }

        return self::NUMBER_WORDS[mb_strtolower($word)] ?? 0;
    }

    // ── Time ─────────────────────────────────────────────────────────────────

    private function parseTime(string $text): ?string
    {
        // Explicit clock time wins: "at 10:30", "at 10.30", "at 9", "at 9am", "at 3pm", "at 9:30am".
        if ((bool) preg_match('/\bat\s+(\d{1,2})(?:[.:](\d{2}))?\s*(am|pm)?\b/i', $text, $m)) {
            $hour = (int) $m[1];
            $min = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
            $ampm = mb_strtolower($m[3] ?? '');

            if ($ampm === 'pm' && $hour < 12) {
                $hour += 12;
            }
            if ($ampm === 'am' && $hour === 12) {
                $hour = 0;
            }

            return sprintf('%02d:%02d', $hour, $min);
        }

        // Fixed day-part mappings (most-specific phrases first).
        $lower = mb_strtolower($text);
        foreach (self::DAY_PARTS as $phrase => $time) {
            if (str_contains($lower, $phrase)) {
                return $time;
            }
        }

        return null;
    }
}
