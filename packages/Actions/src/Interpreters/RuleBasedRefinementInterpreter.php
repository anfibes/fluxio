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

        $mutations = [];

        $temporal = $this->parser->parse($text);
        if (isset($temporal['date'])) {
            $mutations[] = new NormalizedMutation(
                field:     'date',
                label:     'Date',
                value:     $temporal['date'],
                operation: 'replace',
            );
        }
        if (isset($temporal['time'])) {
            $mutations[] = new NormalizedMutation(
                field:     'time',
                label:     'Time',
                value:     $temporal['time'],
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
