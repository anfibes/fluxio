<?php

namespace Fluxio\Actions\DTO;

/**
 * A user-facing entity reference SPAN extracted by command interpretation — what the
 * user referred to, NOT which entity it is (Phase 9E.2).
 *
 * It makes explicit the category distinction that was previously encoded only by the
 * `*_query` naming convention in the untyped entities map: an entity reference is a
 * span that must still pass through EntityResolverRegistry, as opposed to a parsed
 * scalar value (`date`, `time`, `priority`, `assignee`) which is already interpreted.
 *
 * `key` is the entities-map transit key (e.g. `lead_query`), which also serves as the
 * resolver dispatch `entityType` (`ResolutionContext($key)` →
 * `LeadEntityResolver::supports('lead_query')`). It is the compatibility bridge that
 * keeps NormalizedCommand's wire shape unchanged.
 *
 * Core invariant — an EntityReference is interpretation-side and identity-free. It
 * carries the reference span only. It MUST NOT contain entity ids,
 * selected_candidate_id, candidates, a resolved label held as authority, an ambiguity
 * outcome, or any lifecycle state. EntityResolverRegistry remains the only
 * matching/identity authority. (A guardrail test asserts this surface.)
 */
final class EntityReference
{
    public function __construct(
        public readonly string $key,
        public readonly string $value,
    ) {}
}
