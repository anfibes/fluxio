<?php

namespace Fluxio\Actions\DTO\Ambiguity;

/**
 * The structural-authority input for ambiguity resolution (Phase 8C) — the
 * ambiguity-side parallel of NormalizedMutation. It is the validated, lowered
 * form of a SemanticAmbiguityClarification and is consumed by the deterministic
 * AmbiguityResolver authority.
 *
 * It is produced ONLY by AmbiguityDirectiveLowerer (which validates the selector
 * form and forbids identity dimensions). It carries no candidate identity and no
 * outcome; the resolver computes resolved | narrowed | unresolved from it and the
 * current candidate set. It deliberately does NOT lower into NormalizedMutation:
 * ambiguity state and field state are distinct application authorities.
 */
final class AmbiguityDirective
{
    public function __construct(
        public readonly string $ambiguityKey,
        public readonly AmbiguitySelector $selector,
    ) {}
}
