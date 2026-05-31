<?php

namespace Fluxio\Actions\DTO\Ambiguity;

use Fluxio\Actions\Enums\AmbiguitySelectorKind;

/**
 * "Rossi SRL" / "Mario" — selection by the user-visible text the user used. The
 * resolver matches this against candidate LABELS (exact, then unique partial); it
 * never matches against candidate identity. The text is what the user said, not a
 * resolved choice.
 */
final class LabelSelector implements AmbiguitySelector
{
    public function __construct(
        public readonly string $text,
    ) {}

    public function kind(): AmbiguitySelectorKind
    {
        return AmbiguitySelectorKind::Label;
    }
}
