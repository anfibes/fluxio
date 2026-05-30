<?php

namespace Fluxio\Actions\DTO;

use Fluxio\Actions\Enums\SemanticMutationType;

class NormalizedMutation
{
    /**
     * A single mutation produced by a refinement interpreter.
     *
     * @param  string  $operation  Semantic operation: replace | clear | append | remove
     * @param  string  $field  Target field key (date, time, priority, participants, …)
     * @param  string  $label  Human-readable field label
     * @param  mixed  $value  New value for replace/append; null for clear/remove
     * @param  string  $source  Origin of the mutation (detected, inferred, …)
     * @param  float  $confidence  Interpreter confidence [0–1]
     * @param  string|null  $target  Specific item to target within a collection field
     * @param  array<string, mixed>  $metadata  Interpreter-specific extra data
     * @param  SemanticMutationType|null  $semanticType  Explicit semantic type (Phase 7C); when null it is
     *                                                   derived on demand via semanticType(). Descriptive
     *                                                   explainability only — never affects behaviour.
     */
    public function __construct(
        public readonly string $field,
        public readonly string $label,
        public readonly mixed $value,
        public readonly string $operation = 'replace',
        public readonly string $source = 'detected',
        public readonly float $confidence = 1.0,
        public readonly ?string $target = null,
        public readonly array $metadata = [],
        public readonly ?SemanticMutationType $semanticType = null,
    ) {}

    /**
     * Resolved semantic mutation type: the explicit one when set, otherwise
     * classified from this mutation's structure. Always returns a value so
     * callers don't special-case null.
     */
    public function semanticType(): SemanticMutationType
    {
        return $this->semanticType ?? SemanticMutationType::classify($this);
    }
}
