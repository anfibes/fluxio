<?php

namespace Fluxio\Actions\Enums;

use Fluxio\Actions\DTO\NormalizedMutation;

/**
 * Descriptive operational meaning of a refinement mutation (Phase 7C).
 *
 * This is explainability metadata, NOT a second mutation engine: it never
 * changes lifecycle, execution, capability legality, or persistence. It makes
 * the *intent* of a structurally-described mutation explicit — e.g. a
 * `replace time = 10:30` that originated from "push it by 30 minutes" is a
 * `shift_time`, not a plain `replace_time`.
 *
 * Only the narrow first-slice cases are classified; everything else is Unknown.
 */
enum SemanticMutationType: string
{
    case ReplaceTime = 'replace_time';
    case ReplaceDate = 'replace_date';
    case ShiftTime = 'shift_time';
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
