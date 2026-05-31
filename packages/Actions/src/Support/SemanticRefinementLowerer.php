<?php

namespace Fluxio\Actions\Support;

use Fluxio\Actions\DTO\NormalizedMutation;
use Fluxio\Actions\DTO\SemanticRefinementMutation;
use Fluxio\Actions\Enums\SemanticMutationType;
use Fluxio\Actions\Exceptions\CannotLowerSemanticRefinementException;

/**
 * Deterministic lowering from the Semantic Refinement IR (interpretation
 * boundary) to the structural NormalizedMutation (application boundary).
 *
 *   semantic mutation → lower → NormalizedMutation → existing capability/applyAll flow
 *
 * This is the one explicit bridge between the two boundaries (Phase 8B). It is:
 *
 *  - total over the supported, well-formed semantic types and partial ONLY by
 *    explicit rejection — an unsupported type or a malformed payload throws
 *    CannotLowerSemanticRefinementException; it never silently falls back, so an
 *    un-lowerable semantic operation can never reach the application boundary;
 *  - context-free — it produces the SAME structural shapes the rule-based
 *    interpreter produces today. In particular ShiftTime lowers to a
 *    metadata-only `replace time = null` mutation (value intentionally absent):
 *    the concrete time is still computed later by
 *    ActionProposalRefinementService against ProposalRuntimeContext. The lowerer
 *    classifies/normalizes; the runtime computes/validates/applies.
 *
 * It does NOT change runtime behavior in this phase: nothing wires it into the
 * live refinement endpoint yet. It is the foundation a future LLM provider will
 * target (emit semantic IR → lower → run through the unchanged runtime).
 */
class SemanticRefinementLowerer
{
    public function lower(SemanticRefinementMutation $mutation): NormalizedMutation
    {
        return match ($mutation->type) {
            SemanticMutationType::ReplaceTime => $this->scalarReplace($mutation, 'time', 'Time'),
            SemanticMutationType::ReplaceDate => $this->scalarReplace($mutation, 'date', 'Date'),
            SemanticMutationType::ShiftTime => $this->shiftTime($mutation),
            SemanticMutationType::AddParticipant => $this->addParticipant($mutation),
            SemanticMutationType::RemoveParticipant => $this->removeParticipant($mutation),
            SemanticMutationType::ReplaceParticipant => $this->replaceParticipant($mutation),
            SemanticMutationType::Unknown => throw new CannotLowerSemanticRefinementException(
                'Cannot lower an Unknown semantic refinement mutation; the interpretation is not recognized.',
            ),
        };
    }

    private function scalarReplace(SemanticRefinementMutation $mutation, string $field, string $label): NormalizedMutation
    {
        $value = $this->requireStringPayload($mutation, 'value', $field);

        return new NormalizedMutation(
            field: $field,
            label: $label,
            value: $value,
            operation: 'replace',
            source: $mutation->source,
            target: null,
            metadata: $mutation->metadata,
            semanticType: $mutation->type,
        );
    }

    private function shiftTime(SemanticRefinementMutation $mutation): NormalizedMutation
    {
        $amount = $mutation->payload['amount'] ?? null;
        if (! is_int($amount) || $amount <= 0) {
            throw new CannotLowerSemanticRefinementException(
                'shift_time requires a positive integer [payload.amount].',
            );
        }

        $unit = $mutation->payload['unit'] ?? null;
        if (! in_array($unit, ['minutes', 'hours'], true)) {
            throw new CannotLowerSemanticRefinementException(
                "shift_time requires [payload.unit] of 'minutes' or 'hours'.",
            );
        }

        // Direction defaults to 'later' when omitted, but a present value must be
        // a recognized direction — an unknown value is rejected, never normalized.
        $direction = $mutation->payload['direction'] ?? 'later';
        if (! in_array($direction, ['later', 'earlier'], true)) {
            throw new CannotLowerSemanticRefinementException(
                "shift_time [payload.direction] must be 'later' or 'earlier' when present.",
            );
        }

        $minutes = $unit === 'hours' ? $amount * 60 : $amount;

        // Same metadata-only contract as RuleBasedRefinementInterpreter::extractTemporalShift:
        // value stays null; the service resolves the concrete time from the
        // proposal's current time. Always normalized to minutes.
        return new NormalizedMutation(
            field: 'time',
            label: 'Time',
            value: null,
            operation: 'replace',
            source: $mutation->source,
            target: null,
            metadata: array_merge($mutation->metadata, [
                'contextual_operation' => 'temporal_shift',
                'unit' => 'minutes',
                'amount' => $minutes,
                'direction' => $direction,
            ]),
            semanticType: SemanticMutationType::ShiftTime,
        );
    }

    private function addParticipant(SemanticRefinementMutation $mutation): NormalizedMutation
    {
        $value = $this->requireStringPayload($mutation, 'value', 'participants');

        return new NormalizedMutation(
            field: 'participants',
            label: 'Participants',
            value: $value,
            operation: 'append',
            source: $mutation->source,
            target: null,
            metadata: $mutation->metadata,
            semanticType: SemanticMutationType::AddParticipant,
        );
    }

    private function removeParticipant(SemanticRefinementMutation $mutation): NormalizedMutation
    {
        $target = $this->requireTarget($mutation, 'remove_participant');

        return new NormalizedMutation(
            field: 'participants',
            label: 'Participants',
            value: null,
            operation: 'remove',
            source: $mutation->source,
            target: $target,
            metadata: $mutation->metadata,
            semanticType: SemanticMutationType::RemoveParticipant,
        );
    }

    private function replaceParticipant(SemanticRefinementMutation $mutation): NormalizedMutation
    {
        $target = $this->requireTarget($mutation, 'replace_participant');
        $value = $this->requireStringPayload($mutation, 'value', 'participants');

        return new NormalizedMutation(
            field: 'participants',
            label: 'Participants',
            value: $value,
            operation: 'replace',
            source: $mutation->source,
            target: $target,
            metadata: $mutation->metadata,
            semanticType: SemanticMutationType::ReplaceParticipant,
        );
    }

    private function requireStringPayload(SemanticRefinementMutation $mutation, string $key, string $field): string
    {
        $value = $mutation->payload[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new CannotLowerSemanticRefinementException(
                "{$mutation->type->value} requires a non-empty string [payload.{$key}] for [{$field}].",
            );
        }

        return $value;
    }

    private function requireTarget(SemanticRefinementMutation $mutation, string $type): string
    {
        $target = $mutation->target;
        if (! is_string($target) || trim($target) === '') {
            throw new CannotLowerSemanticRefinementException(
                "{$type} requires a non-empty [target] (the existing participant).",
            );
        }

        return $target;
    }
}
