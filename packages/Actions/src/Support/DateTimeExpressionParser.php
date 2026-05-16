<?php

namespace Fluxio\Actions\Support;

use Carbon\Carbon;

class DateTimeExpressionParser
{
    private const WEEKDAYS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
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

        if (str_contains($lower, 'tomorrow')) {
            return now()->addDay()->toDateString();
        }

        foreach (self::WEEKDAYS as $day) {
            if ((bool) preg_match('/\b' . $day . '\b/i', $lower)) {
                return Carbon::parse('next ' . $day)->toDateString();
            }
        }

        return null;
    }

    // ── Time ─────────────────────────────────────────────────────────────────

    private function parseTime(string $text): ?string
    {
        // "at 10:30", "at 10.30", "at 9", "at 9am", "at 3pm", "at 9:30am"
        if ((bool) preg_match('/\bat\s+(\d{1,2})(?:[.:](\d{2}))?\s*(am|pm)?\b/i', $text, $m)) {
            $hour = (int) $m[1];
            $min  = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
            $ampm = mb_strtolower($m[3] ?? '');

            if ($ampm === 'pm' && $hour < 12) {
                $hour += 12;
            }
            if ($ampm === 'am' && $hour === 12) {
                $hour = 0;
            }

            return sprintf('%02d:%02d', $hour, $min);
        }

        // "morning" → 09:00
        if ((bool) preg_match('/\bmorning\b/i', $text)) {
            return '09:00';
        }

        return null;
    }
}
