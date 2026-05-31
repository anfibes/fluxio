<?php

namespace Fluxio\Actions\Enums;

/**
 * The deterministic outcome of applying an ambiguity selector to the current
 * candidate set (Phase 8C). Computed by AmbiguityResolver — never chosen by a
 * provider.
 *
 *  - Resolved   : the selector matched exactly one candidate.
 *  - Narrowed   : the selector matched more than one candidate; the ambiguity
 *                 stays blocking with a reduced candidate set.
 *  - Unresolved : the selector matched no candidate (or is out of range).
 *
 * The same selector can produce different outcomes as the candidate set evolves
 * across refinements — which is exactly why the provider cannot decide it.
 */
enum ResolutionOutcomeType: string
{
    case Resolved = 'resolved';
    case Narrowed = 'narrowed';
    case Unresolved = 'unresolved';
}
