<?php

namespace Fluxio\Actions\Enums;

use Fluxio\Actions\DTO\NormalizedMutation;

/**
 * Descriptive operational meaning of a refinement mutation (Phase 7C / 7D).
 *
 * This is explainability metadata, NOT a second mutation engine: it never
 * changes lifecycle, execution, capability legality, or persistence. It makes
 * the *intent* of a structurally-described mutation explicit — e.g. a
 * `replace time = 10:30` that originated from "push it by 30 minutes" is a
 * `shift_time`, not a plain `replace_time`; and an `append participants = Marco`
 * is an `add_participant`.
 *
 * Only the narrow classified cases are recognized; everything else is Unknown.
 *
 * ARCHITECTURAL DIRECTION (Phase 8B): this enum is the vocabulary of the
 * Semantic Refinement IR (the interpretation boundary, see
 * SemanticRefinementMutation). The intended flow is now one-directional:
 *
 *   semantic mutation (this enum) → SemanticRefinementLowerer → NormalizedMutation
 *
 * `classify()` below derives the semantic type FROM an already-built structural
 * NormalizedMutation. That reverse arrow is TRANSITIONAL / backward-compatible
 * support for Phase 7C/7D (it powers `NormalizedMutation::semanticType()` and the
 * descriptive `last_refinement.changes[].semantic_type` rendering). It is NOT the
 * long-term direction. A future provider (LLM/voice) emits the semantic type as
 * authoritative interpretation output and the lowerer produces structure from it;
 * the runtime must not depend on re-deriving meaning from structure.
 */
enum SemanticMutationType: string
{
    case ReplaceTime = 'replace_time';
    case ReplaceDate = 'replace_date';
    case ShiftTime = 'shift_time';
    case AddParticipant = 'add_participant';
    case RemoveParticipant = 'remove_participant';
    case ReplaceParticipant = 'replace_participant';
    case Unknown = 'unknown';

    /**
     * Classify a mutation's semantic type from its structure.
     *
     * Metadata is inspected first so a contextual temporal shift that has been
     * resolved into a concrete time replace (it still carries
     * `contextual_operation: temporal_shift`) stays identifiable as ShiftTime
     * rather than collapsing into ReplaceTime.
     */
    public static function classify(NormalizedMutation $mutation): self
    {
        if (
            ($mutation->metadata['contextual_operation'] ?? null) === 'temporal_shift'
            && $mutation->field === 'time'
        ) {
            return self::ShiftTime;
        }

        // Participant collection mutations (Phase 7D): make the operational
        // meaning of the existing participants collection operations explicit.
        // A replace is only a participant replace when it targets an item.
        if ($mutation->field === 'participants') {
            $participant = match ($mutation->operation) {
                'append' => self::AddParticipant,
                'remove' => self::RemoveParticipant,
                'replace' => $mutation->target !== null ? self::ReplaceParticipant : null,
                default => null,
            };

            if ($participant !== null) {
                return $participant;
            }
        }

        // Scalar replaces only (a targeted replace is a collection mutation).
        if ($mutation->operation === 'replace' && $mutation->target === null) {
            return match ($mutation->field) {
                'time' => self::ReplaceTime,
                'date' => self::ReplaceDate,
                default => self::Unknown,
            };
        }

        return self::Unknown;
    }
}
