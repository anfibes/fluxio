<?php

namespace Fluxio\Actions\Interpreters;

use Fluxio\Actions\Contracts\RefinementInterpreterInterface;
use Fluxio\Actions\DTO\NormalizedMutation;
use Fluxio\Actions\Support\DateTimeExpressionParser;

class RuleBasedRefinementInterpreter implements RefinementInterpreterInterface
{
    public function __construct(private readonly DateTimeExpressionParser $parser) {}

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

        // Relative temporal shift (e.g. "push it by 30 minutes", "move it one hour
        // earlier"). Detected here but NOT resolved: the interpreter stays stateless
        // and emits a metadata-only mutation. ActionProposalRefinementService resolves
        // it against the proposal's current time via ProposalRuntimeContext.
        $temporalShift = $this->extractTemporalShift($text);
        if ($temporalShift !== null) {
            return [$temporalShift];
        }

        $mutations = [];

        $temporal = $this->parser->parse($text);
        if (isset($temporal['date'])) {
            $mutations[] = new NormalizedMutation(
                field: 'date',
                label: 'Date',
                value: $temporal['date'],
                operation: 'replace',
            );
        }
        if (isset($temporal['time'])) {
            $mutations[] = new NormalizedMutation(
                field: 'time',
                label: 'Time',
                value: $temporal['time'],
                operation: 'replace',
            );
        }

        $priority = $this->extractPriority($text);
        if ($priority !== null) {
            $mutations[] = new NormalizedMutation(
                field: 'priority',
                label: 'Priority',
                value: $priority,
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
                field: 'participants',
                label: 'Participants',
                value: trim($m[2]),
                operation: 'replace',
                target: trim($m[1]),
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
                field: 'participants',
                label: 'Participants',
                value: null,
                operation: 'remove',
                target: trim($m[1]),
            );
        }

        return null;
    }

    private function extractCollectionAppend(string $text): ?NormalizedMutation
    {
        // "Add Mario too" or "Also add Mario"
        if ((bool) preg_match('/\badd\s+(.+?)\s+too\b/i', $text, $m)) {
            return new NormalizedMutation(
                field: 'participants',
                label: 'Participants',
                value: trim($m[1]),
                operation: 'append',
            );
        }

        if ((bool) preg_match('/\balso\s+add\s+(.+)/i', $text, $m)) {
            return new NormalizedMutation(
                field: 'participants',
                label: 'Participants',
                value: trim($m[1]),
                operation: 'append',
            );
        }

        return null;
    }

    // ── Relative temporal shift (Phase 7B) ──────────────────────────────────
    // Narrow, explicit, deterministic detection only. No vague expressions
    // ("later", "soon", "after lunch", …) and no day/week shifts. The value is
    // left null; the concrete time is computed by the service from the proposal's
    // current time. Returns a metadata-only mutation describing the requested shift.

    /** @var array<string, int> Word → number for the small supported quantity set. */
    private const NUMBER_WORDS = [
        'one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5,
        'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10,
        'eleven' => 11, 'twelve' => 12,
    ];

    private function extractTemporalShift(string $text): ?NormalizedMutation
    {
        // Require an explicit "push/move/make it" trigger to stay narrow.
        if (! (bool) preg_match('/\b(?:push|move|make)\s+it\b/i', $text)) {
            return null;
        }

        // Require an explicit quantity + unit (minutes / hours).
        if (! (bool) preg_match('/\b(\d+|one|two|three|four|five|six|seven|eight|nine|ten|eleven|twelve)\s*(hours?|minutes?|mins?)\b/i', $text, $m)) {
            return null;
        }

        $amount = $this->wordToNumber($m[1]);
        if ($amount <= 0) {
            return null;
        }

        $isHours = str_starts_with(mb_strtolower($m[2]), 'hour');
        $minutes = $isHours ? $amount * 60 : $amount;

        // Direction: "earlier" shifts backward; everything else (including
        // "later" and the bare "push it by …") shifts forward.
        $direction = (bool) preg_match('/\bearlier\b/i', $text) ? 'earlier' : 'later';

        return new NormalizedMutation(
            field: 'time',
            label: 'Time',
            value: null,
            operation: 'replace',
            source: 'inferred',
            metadata: [
                'contextual_operation' => 'temporal_shift',
                'unit' => 'minutes',
                'amount' => $minutes,
                'direction' => $direction,
            ],
        );
    }

    private function wordToNumber(string $word): int
    {
        if (is_numeric($word)) {
            return (int) $word;
        }

        return self::NUMBER_WORDS[mb_strtolower($word)] ?? 0;
    }

    // ── Clear operations ─────────────────────────────────────────────────────

    private function extractClearMutation(string $text): ?NormalizedMutation
    {
        $lower = mb_strtolower($text);

        if (
            str_contains($lower, 'remove priority') ||
            str_contains($lower, 'clear priority') ||
            str_contains($lower, 'no priority')
        ) {
            return new NormalizedMutation(
                field: 'priority',
                label: 'Priority',
                value: null,
                operation: 'clear',
            );
        }

        return null;
    }

    // ── Replace extraction ───────────────────────────────────────────────────

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
