<?php

namespace Fluxio\Actions\DTO;

use Fluxio\Actions\Enums\RefinementAmbiguityOutcomeKind;

/**
 * Inert reporting facet describing what a refinement turn did to the
 * ambiguity-state authority (Phase 8E.1). It is the ambiguity counterpart of the
 * field-state authority's last_refinement.changes[] list — last_refinement
 * becomes the union projection of both authorities.
 *
 * Hard invariants (this type is REPORT, not authority):
 *  - derived and behaviorally inert — written for the response only, never read
 *    back by the runtime, never used for next-turn / lifecycle / readiness logic;
 *  - never authoritative — the candidate set stays in proposal->ambiguities[];
 *    this carries counts, never the candidate array and never selected ids;
 *  - provider-blind — no provider/provenance/confidence/trust surface; it names a
 *    deterministic state transition, not who produced it.
 *
 * It does NOT duplicate the candidate list: narrowing is reported as from/to
 * counts only.
 */
final class RefinementAmbiguityOutcome
{
    private function __construct(
        public readonly RefinementAmbiguityOutcomeKind $kind,
        public readonly ?string $ambiguityKey = null,
        public readonly ?string $label = null,
        public readonly ?int $fromCount = null,
        public readonly ?int $toCount = null,
    ) {}

    public static function resolved(string $ambiguityKey, string $label): self
    {
        return new self(RefinementAmbiguityOutcomeKind::Resolved, $ambiguityKey, $label);
    }

    public static function narrowed(string $ambiguityKey, string $label, int $fromCount, int $toCount): self
    {
        return new self(RefinementAmbiguityOutcomeKind::Narrowed, $ambiguityKey, $label, $fromCount, $toCount);
    }

    public static function unresolved(string $ambiguityKey, string $label): self
    {
        return new self(RefinementAmbiguityOutcomeKind::Unresolved, $ambiguityKey, $label);
    }

    /**
     * Orchestration-level: a clarification was understood but no blocking ambiguity
     * was applicable, or the intent does not support resolution. The key/label may
     * be unknown, hence optional.
     */
    public static function inapplicable(?string $ambiguityKey = null, ?string $label = null): self
    {
        return new self(RefinementAmbiguityOutcomeKind::Inapplicable, $ambiguityKey, $label);
    }

    /**
     * Minimal presentation payload. Null fields are omitted so each kind carries
     * only what is meaningful (e.g. narrowed carries counts; inapplicable may carry
     * neither key nor counts). `kind` is always present.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'kind' => $this->kind->value,
            'ambiguity_key' => $this->ambiguityKey,
            'label' => $this->label,
            'from_count' => $this->fromCount,
            'to_count' => $this->toCount,
        ], fn ($value) => $value !== null);
    }
}
