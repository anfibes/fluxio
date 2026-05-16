<?php

namespace Fluxio\Actions\Interpreters;

use Carbon\Carbon;
use Fluxio\Actions\Contracts\RefinementInterpreterInterface;
use Fluxio\Actions\DTO\NormalizedMutation;

class RuleBasedRefinementInterpreter implements RefinementInterpreterInterface
{
    /**
     * Extract normalized mutations from a refinement text.
     *
     * The interpreter is intentionally locale-fixed to English for now.
     * Future implementations (Italian, German, LLM-assisted) can implement
     * RefinementInterpreterInterface without touching the orchestration layer.
     *
     * @return NormalizedMutation[]
     */
    public function interpret(string $text): array
    {
        // Clear operations win over everything else for the same field.
        $clearMutation = $this->extractClearMutation($text);
        if ($clearMutation !== null) {
            return [$clearMutation];
        }

        // Collection mutations are exclusive and returned immediately to prevent
        // them from being combined with scalar field mutations.
        $collectionReplace = $this->extractCollectionReplace($text);
        if ($collectionReplace !== null) {
            return [$collectionReplace];
        }

        $collectionRemove = $this->extractCollectionRemove($text);
        if ($collectionRemove !== null) {
            return [$collectionRemove];
        }

        $collectionAppend = $this->extractCollectionAppend($text);
        if ($collectionAppend !== null) {
            return [$collectionAppend];
        }

        $mutations = [];

        $date = $this->extractDate($text);
        if ($date !== null) {
            $mutations[] = new NormalizedMutation(
                field:     'date',
                label:     'Date',
                value:     $date,
                operation: 'replace',
            );
        }

        $time = $this->extractTime($text);
        if ($time !== null) {
            $mutations[] = new NormalizedMutation(
                field:     'time',
                label:     'Time',
                value:     $time,
                operation: 'replace',
            );
        }

        $priority = $this->extractPriority($text);
        if ($priority !== null) {
            $mutations[] = new NormalizedMutation(
                field:     'priority',
                label:     'Priority',
                value:     $priority,
                operation: 'replace',
            );
        }

        return $mutations;
    }

    // ── Collection mutations ─────────────────────────────────────────────────
    // These patterns target the `participants` collection field.
    // The interpreter is stateless: it does not inspect the proposal.
    // The service applies the mutation and handles missing-item no-ops.

    private function extractCollectionReplace(string $text): ?NormalizedMutation
    {
        // "Replace Luca with Marco" / "Replace Luca Bianchi with Marco Rossi"
        if ((bool) preg_match('/\breplace\s+(.+?)\s+with\s+(.+)\s*$/i', $text, $m)) {
            return new NormalizedMutation(
                field:     'participants',
                label:     'Participants',
                value:     trim($m[2]),
                operation: 'replace',
                target:    trim($m[1]),
            );
        }

        return null;
    }

    private function extractCollectionRemove(string $text): ?NormalizedMutation
    {
        // "Remove Marco" / "Remove Marco Polo"
        // Note: "Remove priority" is caught by extractClearMutation before this runs.
        if ((bool) preg_match('/\bremove\s+(\w+(?:\s+\w+)*)\s*$/i', $text, $m)) {
            return new NormalizedMutation(
                field:     'participants',
                label:     'Participants',
                value:     null,
                operation: 'remove',
                target:    trim($m[1]),
            );
        }

        return null;
    }

    private function extractCollectionAppend(string $text): ?NormalizedMutation
    {
        // "Add Mario too" or "Also add Mario"
        if ((bool) preg_match('/\badd\s+(.+?)\s+too\b/i', $text, $m)) {
            return new NormalizedMutation(
                field:     'participants',
                label:     'Participants',
                value:     trim($m[1]),
                operation: 'append',
            );
        }

        if ((bool) preg_match('/\balso\s+add\s+(.+)/i', $text, $m)) {
            return new NormalizedMutation(
                field:     'participants',
                label:     'Participants',
                value:     trim($m[1]),
                operation: 'append',
            );
        }

        return null;
    }

    // ── Clear operations ─────────────────────────────────────────────────────

    private function extractClearMutation(string $text): ?NormalizedMutation
    {
        $lower = mb_strtolower($text);

        if (
            str_contains($lower, 'remove priority') ||
            str_contains($lower, 'clear priority')  ||
            str_contains($lower, 'no priority')
        ) {
            return new NormalizedMutation(
                field:     'priority',
                label:     'Priority',
                value:     null,
                operation: 'clear',
            );
        }

        return null;
    }

    // ── Replace extraction ───────────────────────────────────────────────────

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
