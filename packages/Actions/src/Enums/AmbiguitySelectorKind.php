<?php

namespace Fluxio\Actions\Enums;

/**
 * Discriminator for the Ambiguity Clarification IR selector union (Phase 8C).
 *
 * A selector expresses HOW the user referred to a candidate — by presentation
 * order, by user-visible text, or by a candidate attribute. It never references
 * candidate identity (no IDs). The deterministic AmbiguityResolver computes the
 * outcome from the selector and the current candidate set.
 */
enum AmbiguitySelectorKind: string
{
    case Ordinal = 'ordinal';
    case Label = 'label';
    case Attribute = 'attribute';
}
