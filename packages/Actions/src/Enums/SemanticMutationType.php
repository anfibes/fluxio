<?php

namespace Fluxio\Actions\Enums;

use Fluxio\Actions\DTO\NormalizedMutation;

/**
 * The semantic mutation vocabulary of the Semantic Refinement IR — the
 * interpretation boundary (see SemanticRefinementMutation).
 *
 * As of the Phase 8D arrow flip this enum is the AUTHORITATIVE interpretation
 * output the interpreter emits, and the runtime flow is one-directional:
 *
 *   semantic mutation (this enum) → SemanticRefinementLowerer → NormalizedMutation → applyAll
 *
 * Migrated runtime paths carry the semantic type THROUGH lowering (the lowered
 * NormalizedMutation holds it explicitly); the runtime never re-derives meaning
 * from structure. It still never changes lifecycle, execution, capability
 * legality, or persistence — it names the *intent* of a mutation (e.g. a
 * `replace time = 10:30` from "push it by 30 minutes" is a `shift_time`, not a
 * plain `replace_time`).
 *
 * `classify()` below derives the semantic type FROM an already-built structural
 * NormalizedMutation — the REVERSE arrow. It is TRANSITIONAL / backward-compatible
 * support only (Phase 7C/7D): it backs `NormalizedMutation::semanticType()` as a
 * fallback when no explicit type is set, and descriptive rendering. It must NOT be
 * broadened or treated as the primary path, and migrated paths do not depend on it.
 * Only the narrow classified cases are recognized there; everything else is Unknown.
 */
enum SemanticMutationType: string
{
    case ReplaceTime = 'replace_time';
    case ReplaceDate = 'replace_date';
    case ShiftTime = 'shift_time';
    case AddParticipant = 'add_participant';
    case RemoveParticipant = 'remove_participant';
    case ReplaceParticipant = 'replace_participant';
    case ReplacePriority = 'replace_priority';
    case ClearPriority = 'clear_priority';
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
