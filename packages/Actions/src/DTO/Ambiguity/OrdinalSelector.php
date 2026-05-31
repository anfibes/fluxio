<?php

namespace Fluxio\Actions\DTO\Ambiguity;

use Fluxio\Actions\Enums\AmbiguitySelectorKind;

/**
 * "The first/second one" — selection by 1-based presentation position. The
 * position refers to the CURRENT (possibly already-narrowed) candidate order,
 * which the runtime keeps as a stable total order. Validation (position >= 1)
 * happens at the lowering boundary, not here.
 */
final class OrdinalSelector implements AmbiguitySelector
{
    public function __construct(
        public readonly int $position,
    ) {}

    public function kind(): AmbiguitySelectorKind
    {
        return AmbiguitySelectorKind::Ordinal;
    }
}
