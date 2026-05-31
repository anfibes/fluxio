<?php

namespace Fluxio\Actions\DTO\Ambiguity;

use Fluxio\Actions\Enums\ResolutionOutcomeType;

/**
 * The deterministic result of AmbiguityResolver applying a selector to the
 * current candidate set (Phase 8C). It is data, not authority: the resolver
 * computes it; a caller (a future runtime integration) applies it to proposal
 * state. `selectedCandidate` carries the full candidate (including its
 * server-owned id) ONLY when the resolver itself matched it — identity is thus
 * produced by the runtime, never supplied by interpretation.
 */
final class ResolutionOutcome
{
    /**
     * @param  array<string, mixed>|null  $selectedCandidate  Set only when Resolved
     * @param  array<int, array<string, mixed>>  $narrowedCandidates  The remaining subset when Narrowed (order preserved)
     * @param  string|null  $reason  Diagnostic reason when Unresolved (e.g. 'no_match', 'out_of_range')
     */
    private function __construct(
        public readonly ResolutionOutcomeType $type,
        public readonly ?array $selectedCandidate = null,
        public readonly array $narrowedCandidates = [],
        public readonly ?string $reason = null,
    ) {}

    /**
     * @param  array<string, mixed>  $candidate
     */
    public static function resolved(array $candidate): self
    {
        return new self(ResolutionOutcomeType::Resolved, selectedCandidate: $candidate);
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     */
    public static function narrowed(array $candidates): self
    {
        return new self(ResolutionOutcomeType::Narrowed, narrowedCandidates: array_values($candidates));
    }

    public static function unresolved(?string $reason = null): self
    {
        return new self(ResolutionOutcomeType::Unresolved, reason: $reason);
    }

    public function isResolved(): bool
    {
        return $this->type === ResolutionOutcomeType::Resolved;
    }

    public function isNarrowed(): bool
    {
        return $this->type === ResolutionOutcomeType::Narrowed;
    }

    public function isUnresolved(): bool
    {
        return $this->type === ResolutionOutcomeType::Unresolved;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'selected_candidate' => $this->selectedCandidate,
            'narrowed_candidates' => $this->narrowedCandidates,
            'reason' => $this->reason,
        ];
    }
}
