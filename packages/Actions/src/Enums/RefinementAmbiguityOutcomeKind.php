<?php

namespace Fluxio\Actions\Enums;

/**
 * The closed, provider-blind vocabulary for how a refinement turn touched the
 * ambiguity-state authority (Phase 8E.1). It is REPORTING metadata only: it names
 * what happened to the proposal's ambiguity state, never who produced it (no
 * provider/confidence/provenance) and is never read back by the runtime.
 *
 * Three kinds mirror the deterministic AmbiguityResolver's ResolutionOutcomeType
 * (resolved | narrowed | unresolved); the fourth is orchestration-level:
 *
 *  - Resolved     : the resolver selected exactly one candidate (proposal state
 *                   resolution is applied as before — this only names it).
 *  - Narrowed     : the resolver reduced the candidate set but the ambiguity stays
 *                   blocking. The authoritative set is proposal->ambiguities.
 *  - Unresolved   : the resolver ran but the selector matched nothing / was out of
 *                   range; no state change.
 *  - Inapplicable : a clarification was understood but there was no applicable
 *                   blocking ambiguity, or the intent does not support ambiguity
 *                   resolution. Decided by the service, not the resolver.
 */
enum RefinementAmbiguityOutcomeKind: string
{
    case Resolved = 'resolved';
    case Narrowed = 'narrowed';
    case Unresolved = 'unresolved';
    case Inapplicable = 'inapplicable';
}
