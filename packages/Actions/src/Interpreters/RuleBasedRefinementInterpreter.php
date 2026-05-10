<?php

namespace Fluxio\Actions\Interpreters;

use Carbon\Carbon;
use Fluxio\Actions\Contracts\RefinementInterpreterInterface;
use Fluxio\Actions\DTO\NormalizedMutation;

class RuleBasedRefinementInterpreter implements RefinementInterpreterInterface
{
    /**
     * Extract normalized field mutations from a refinement text.
     *
     * The interpreter is intentionally locale-fixed to English for now.
     * Future implementations can add Italian, German, or LLM-assisted variants
     * behind the same interface without touching the orchestration layer.
     *
     * @return NormalizedMutation[]
     */
    public function interpret(string $text): array
    {
        $mutations = [];

        $date = $this->extractDate($text);
        if ($date !== null) {
            $mutations[] = new NormalizedMutation(field: 'date', label: 'Date', value: $date);
        }

        $time = $this->extractTime($text);
        if ($time !== null) {
            $mutations[] = new NormalizedMutation(field: 'time', label: 'Time', value: $time);
        }

        $priority = $this->extractPriority($text);
        if ($priority !== null) {
            $mutations[] = new NormalizedMutation(field: 'priority', label: 'Priority', value: $priority);
        }

        return $mutations;
    }

    private function extractDate(string $text): ?string
    {
        $lower = mb_strtolower(trim($text));

        if (str_contains($lower, 'tomorrow')) {
            return now()->addDay()->toDateString();
        }

        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        foreach ($days as $day) {
            if ((bool) preg_match('/\b' . $day . '\b/i', $lower)) {
                return Carbon::parse('next ' . $day)->toDateString();
            }
        }

        return null;
    }

    private function extractTime(string $text): ?string
    {
        // Explicit: "at 10:30", "at 10.30", "at 9", "at 9am", "at 9pm", "at 9:30am"
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

        // Implicit: "morning" → 09:00
        if ((bool) preg_match('/\bmorning\b/i', $text)) {
            return '09:00';
        }

        return null;
    }

    private function extractPriority(string $text): ?string
    {
        $lower = mb_strtolower($text);

        if (str_contains($lower, 'high priority') || str_contains($lower, 'urgent')) {
            return 'high';
        }

        if (str_contains($lower, 'low priority')) {
            return 'low';
        }

        return null;
    }
}
