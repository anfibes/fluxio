<?php

namespace Fluxio\Actions\Support;

use Fluxio\Actions\DTO\Ambiguity\AmbiguityDirective;
use Fluxio\Actions\DTO\Ambiguity\AttributeSelector;
use Fluxio\Actions\DTO\Ambiguity\LabelSelector;
use Fluxio\Actions\DTO\Ambiguity\OrdinalSelector;
use Fluxio\Actions\DTO\Ambiguity\SemanticAmbiguityClarification;
use Fluxio\Actions\Exceptions\CannotLowerAmbiguityClarificationException;

/**
 * Deterministic lowering of an ambiguity clarification (interpretation IR) into a
 * validated AmbiguityDirective (the AmbiguityResolver authority input) — the
 * ambiguity-side parallel of SemanticRefinementLowerer.
 *
 *   SemanticAmbiguityClarification → lower() → AmbiguityDirective → AmbiguityResolver
 *
 * It validates FORM only and is partial only by explicit rejection: a missing key
 * or malformed selector throws CannotLowerAmbiguityClarificationException. It does
 * NOT resolve candidates, select ids, narrow, or touch proposal state. Crucially
 * it forbids attribute selectors on identity dimensions, so the attribute path can
 * never be used to select a candidate by id.
 */
class AmbiguityDirectiveLowerer
{
    /**
     * Candidate fields that are server-owned identity and must never be used as a
     * selector dimension (no candidate-id backdoor through the attribute path).
     */
    private const FORBIDDEN_DIMENSIONS = ['id', 'candidate_id', 'selected_candidate_id'];

    public function lower(SemanticAmbiguityClarification $clarification): AmbiguityDirective
    {
        if (trim($clarification->ambiguityKey) === '') {
            throw new CannotLowerAmbiguityClarificationException(
                'Ambiguity clarification requires a non-empty [ambiguityKey].',
            );
        }

        $selector = $clarification->selector;

        match (true) {
            $selector instanceof OrdinalSelector => $this->validateOrdinal($selector),
            $selector instanceof LabelSelector => $this->validateLabel($selector),
            $selector instanceof AttributeSelector => $this->validateAttribute($selector),
            default => throw new CannotLowerAmbiguityClarificationException(
                'Unsupported ambiguity selector; cannot lower the clarification.',
            ),
        };

        return new AmbiguityDirective(
            ambiguityKey: $clarification->ambiguityKey,
            selector: $selector,
        );
    }

    private function validateOrdinal(OrdinalSelector $selector): void
    {
        if ($selector->position < 1) {
            throw new CannotLowerAmbiguityClarificationException(
                'Ordinal selector [position] must be a 1-based positive integer.',
            );
        }
    }

    private function validateLabel(LabelSelector $selector): void
    {
        if (trim($selector->text) === '') {
            throw new CannotLowerAmbiguityClarificationException(
                'Label selector requires non-empty [text].',
            );
        }
    }

    private function validateAttribute(AttributeSelector $selector): void
    {
        if (trim($selector->dimension) === '' || trim($selector->value) === '') {
            throw new CannotLowerAmbiguityClarificationException(
                'Attribute selector requires non-empty [dimension] and [value].',
            );
        }

        // Normalize (trim + lowercase) before the identity check so values like
        // " id " or "ID" cannot bypass the forbidden-dimension list.
        $normalizedDimension = mb_strtolower(trim($selector->dimension));
        if (in_array($normalizedDimension, self::FORBIDDEN_DIMENSIONS, true)) {
            throw new CannotLowerAmbiguityClarificationException(
                "Attribute selector [dimension] '{$selector->dimension}' targets candidate identity and is forbidden.",
            );
        }
    }
}
