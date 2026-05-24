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

    /** Parser-local confidence by recognition kind (temporal parse confidence only). */
    private const CONFIDENCE = [
        'explicit' => 1.0,
        'relative' => 0.9,
        'weekday' => 0.85,
        'day_part' => 0.75,
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

        $date = $this->detectDate($text);
        if ($date !== null) {
            $result['date'] = $date['value'];
        }

        $time = $this->detectTime($text);
        if ($time !== null) {
            $result['time'] = $time['value'];
        }

        return $result;
    }

    /**
     * Parse temporal expressions and explain how each value was derived.
     *
     * Same recognition logic as parse(); adds provenance (source + matched
     * expression), human-readable explanations, and a parser-local confidence.
     * Nothing in the proposal pipeline consumes this today — see
     * TemporalParseResult for the strict scope of `confidence`.
     */
    public function explain(string $text): TemporalParseResult
    {
        $date = $this->detectDate($text);
        $time = $this->detectTime($text);

        $explanations = [];
        $confidences = [];

        if ($date !== null) {
            $confidences[] = self::CONFIDENCE[$date['source']];
            $explanations[] = $date['source'] === 'weekday'
                ? "Date resolved from weekday expression '{$date['expression']}' as {$date['value']}."
                : "Date from relative expression '{$date['expression']}' as {$date['value']}.";
        }

        if ($time !== null) {
            $confidences[] = self::CONFIDENCE[$time['source']];
            $explanations[] = $time['source'] === 'day_part'
                ? "Time inferred from '{$time['expression']}' as {$time['value']}."
                : "Time is explicit ('{$time['expression']}') as {$time['value']}.";
        }

        $confidence = $confidences === []
            ? 0.0
            : round(array_sum($confidences) / count($confidences), 4);

        return new TemporalParseResult(
            date: $date['value'] ?? null,
            time: $time['value'] ?? null,
            dateSource: $date['source'] ?? 'none',
            timeSource: $time['source'] ?? 'none',
            dateExpression: $date['expression'] ?? null,
            timeExpression: $time['expression'] ?? null,
            explanations: $explanations,
            confidence: $confidence,
        );
    }

    // ── Date ─────────────────────────────────────────────────────────────────

    /**
     * @return array{value: string, source: string, expression: string}|null
     */
    private function detectDate(string $text): ?array
    {
        $lower = mb_strtolower(trim($text));

        // Order matters: more specific phrases first.
        if (str_contains($lower, 'day after tomorrow')) {
            return ['value' => now()->addDays(2)->toDateString(), 'source' => 'relative', 'expression' => 'day after tomorrow'];
        }

        if (str_contains($lower, 'tomorrow')) {
            return ['value' => now()->addDay()->toDateString(), 'source' => 'relative', 'expression' => 'tomorrow'];
        }

        // "in two days" / "in 2 days"
        if ((bool) preg_match('/\bin\s+(\d+|one|two|three|four|five|six|seven)\s+days?\b/i', $lower, $m)) {
            return [
                'value' => now()->addDays($this->wordToNumber($m[1]))->toDateString(),
                'source' => 'relative',
                'expression' => trim($m[0]),
            ];
        }

        // "today" (also covers "later today"); guarded so it never matches inside other words.
        if ((bool) preg_match('/\btoday\b/i', $lower)) {
            return ['value' => now()->toDateString(), 'source' => 'relative', 'expression' => 'today'];
        }

        // Weekday, with optional "next"/"this" qualifier.
        foreach (self::WEEKDAYS as $index => $day) {
            if ((bool) preg_match('/\b(next|this)?\s*'.$day.'\b/i', $lower, $m)) {
                return [
                    'value' => $this->resolveWeekday($index, mb_strtolower(trim($m[1] ?? ''))),
                    'source' => 'weekday',
                    'expression' => trim(preg_replace('/\s+/', ' ', mb_strtolower($m[0]))),
                ];
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

    /**
     * @return array{value: string, source: string, expression: string}|null
     */
    private function detectTime(string $text): ?array
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

            return [
                'value' => sprintf('%02d:%02d', $hour, $min),
                'source' => 'explicit',
                'expression' => trim(preg_replace('/\s+/', ' ', mb_strtolower($m[0]))),
            ];
        }

        // Fixed day-part mappings (most-specific phrases first).
        $lower = mb_strtolower($text);
        foreach (self::DAY_PARTS as $phrase => $time) {
            if (str_contains($lower, $phrase)) {
                return ['value' => $time, 'source' => 'day_part', 'expression' => $phrase];
            }
        }

        return null;
    }
}
