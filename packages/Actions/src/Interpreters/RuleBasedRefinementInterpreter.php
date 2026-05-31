<?php

namespace Fluxio\Actions\Interpreters;

use Fluxio\Actions\Contracts\RefinementInterpreterInterface;
use Fluxio\Actions\DTO\NormalizedMutation;
use Fluxio\Actions\DTO\SemanticRefinementMutation;
use Fluxio\Actions\Enums\SemanticMutationType;
use Fluxio\Actions\Support\DateTimeExpressionParser;

class RuleBasedRefinementInterpreter implements RefinementInterpreterInterface
{
    public function __construct(private readonly DateTimeExpressionParser $parser) {}

    /**
     * Extract refinement operations from a refinement text.
     *
     * Phase 8D.2: a semantic extractor for the migrated mutation families — it
     * emits SemanticRefinementMutation for date/time, temporal shift, and
     * participant add/remove/replace, and the service lowers them. Priority
     * replace/clear are not yet migrated and stay structural NormalizedMutation.
     * The interpreter never constructs the structural shape for migrated families
     * and never resolves anything (the temporal shift stays value-less).
     *
     * The interpreter is intentionally locale-fixed to English for now.
     * Future implementations (Italian, German, LLM-assisted) can implement
     * RefinementInterpreterInterface without touching the orchestration layer.
     *
     * @return array<SemanticRefinementMutation|NormalizedMutation>
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
            $mutations[] = new SemanticRefinementMutation(
                type: SemanticMutationType::ReplaceDate,
                payload: ['value' => $temporal['date']],
            );
        }
        if (isset($temporal['time'])) {
            $mutations[] = new SemanticRefinementMutation(
                type: SemanticMutationType::ReplaceTime,
                payload: ['value' => $temporal['time']],
            );
        }

        // Priority replace is NOT yet migrated to the semantic IR (no priority
        // semantic type / lowering), so it stays structural and passes through
        // the service seam unchanged.
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

    private function extractCollectionReplace(string $text): ?SemanticRefinementMutation
    {
        // "Replace Luca with Marco" / "Replace Luca Bianchi with Marco Rossi"
        if ((bool) preg_match('/\breplace\s+(.+?)\s+with\s+(.+)\s*$/i', $text, $m)) {
            return new SemanticRefinementMutation(
                type: SemanticMutationType::ReplaceParticipant,
                payload: ['value' => trim($m[2])],
                target: trim($m[1]),
            );
        }

        return null;
    }

    private function extractCollectionRemove(string $text): ?SemanticRefinementMutation
    {
        // "Remove Marco" / "Remove Marco Polo"
        // Note: "Remove priority" is caught by extractClearMutation before this runs.
        if ((bool) preg_match('/\bremove\s+(\w+(?:\s+\w+)*)\s*$/i', $text, $m)) {
            return new SemanticRefinementMutation(
                type: SemanticMutationType::RemoveParticipant,
                target: trim($m[1]),
            );
        }

        return null;
    }

    private function extractCollectionAppend(string $text): ?SemanticRefinementMutation
    {
        // "Add Mario too" or "Also add Mario"
        if ((bool) preg_match('/\badd\s+(.+?)\s+too\b/i', $text, $m)) {
            return new SemanticRefinementMutation(
                type: SemanticMutationType::AddParticipant,
                payload: ['value' => trim($m[1])],
            );
        }

        if ((bool) preg_match('/\balso\s+add\s+(.+)/i', $text, $m)) {
            return new SemanticRefinementMutation(
                type: SemanticMutationType::AddParticipant,
                payload: ['value' => trim($m[1])],
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

    private function extractTemporalShift(string $text): ?SemanticRefinementMutation
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

        // Direction: "earlier" shifts backward; everything else (including
        // "later" and the bare "push it by …") shifts forward.
        $direction = (bool) preg_match('/\bearlier\b/i', $text) ? 'earlier' : 'later';

        // Pure extraction: emit the surface amount + unit. Normalizing hours→minutes
        // and computing the concrete time are NOT the interpreter's job — the
        // lowerer normalizes to minutes and the service resolves the time against
        // ProposalRuntimeContext. value stays absent.
        return new SemanticRefinementMutation(
            type: SemanticMutationType::ShiftTime,
            payload: [
                'amount' => $amount,
                'unit' => str_starts_with(mb_strtolower($m[2]), 'hour') ? 'hours' : 'minutes',
                'direction' => $direction,
            ],
            source: 'inferred',
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
