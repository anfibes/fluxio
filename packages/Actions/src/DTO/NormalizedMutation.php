<?php

namespace Fluxio\Actions\DTO;

use Fluxio\Actions\Enums\SemanticMutationType;

class NormalizedMutation
{
    /**
     * Structural mutation — the field-state authority input (applyAll). It is
     * produced only as lowering output (SemanticRefinementLowerer) and must stay
     * provider-blind: no provider/provenance/confidence/trust surface. `metadata`
     * carries ONLY deterministic runtime keys built by the lowerer (e.g. a temporal
     * shift's contextual_operation/unit/amount/direction) — never opaque provider
     * metadata, which is stripped at the lowering boundary.
     *
     * @param  string  $operation  Structural operation the application authority applies:
     *                             replace | clear | append | remove. The semantic MEANING
     *                             (e.g. shift_time vs replace_time) is carried separately
     *                             via $semanticType, not by this operation.
     * @param  string  $field  Target field key (date, time, priority, participants, …)
     * @param  string  $label  Human-readable field label
     * @param  mixed  $value  New value for replace/append; null for clear/remove
     * @param  string  $source  Origin of the mutation (detected, inferred, computed, …)
     * @param  string|null  $target  Specific item to target within a collection field
     * @param  array<string, mixed>  $metadata  Deterministic runtime metadata only (see above)
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
