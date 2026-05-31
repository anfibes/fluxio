<?php

namespace Fluxio\Actions\DTO\Ambiguity;

use Fluxio\Actions\Enums\AmbiguitySelectorKind;

/**
 * Tagged-union marker for an ambiguity selector (Phase 8C interpretation IR).
 *
 * A selector is how the user referred to a candidate. It is deliberately a small
 * closed family — OrdinalSelector | LabelSelector | AttributeSelector — and
 * carries NO candidate identity: a provider can express "the first one",
 * "Rossi SRL", or "the company", but never a candidate id. The deterministic
 * AmbiguityResolver maps a selector to a concrete candidate; identity is owned by
 * the runtime, never by interpretation.
 */
interface AmbiguitySelector
{
    public function kind(): AmbiguitySelectorKind;
}
