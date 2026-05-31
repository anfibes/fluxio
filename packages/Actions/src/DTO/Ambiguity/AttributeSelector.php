<?php

namespace Fluxio\Actions\DTO\Ambiguity;

use Fluxio\Actions\Enums\AmbiguitySelectorKind;

/**
 * "The company" / "the person" — selection by a candidate attribute dimension and
 * value (e.g. dimension="type", value="company"). This generalizes type narrowing
 * WITHOUT hardcoding company/person: the resolver filters generically on
 * candidate[dimension] === value, and the dimension/value vocabulary is data
 * carried by candidates, not branches in code.
 *
 * Identity dimensions (e.g. "id") are forbidden at the lowering boundary so the
 * attribute selector can never become a candidate-id backdoor.
 */
final class AttributeSelector implements AmbiguitySelector
{
    public function __construct(
        public readonly string $dimension,
        public readonly string $value,
    ) {}

    public function kind(): AmbiguitySelectorKind
    {
        return AmbiguitySelectorKind::Attribute;
    }
}
