<?php

namespace Fluxio\Actions\DTO;

use Fluxio\Actions\EntityResolution\DTO\ResolutionCandidate;

/**
 * A server-owned resolved entity identity persisted on the proposal
 * (`resolved_entities[key]` — identity continuity, slice 1).
 *
 * It is the runtime-side counterpart of ResolutionCandidate: `id` is the real
 * primary key produced by an entity resolver (auto-resolution) or selected via
 * the ambiguity-state authority — never supplied by a provider or by the user.
 * `type` and `label` are descriptive snapshots taken at resolution time; only
 * `id` is identity, and executors consuming this contract look the record up by
 * primary key without ever comparing the label.
 */
final class ResolvedEntity
{
    public function __construct(
        public readonly int|string $id,
        public readonly string $type,
        public readonly string $label,
    ) {}

    public static function fromCandidate(ResolutionCandidate $candidate): self
    {
        return new self(
            id: $candidate->id,
            type: $candidate->type,
            label: $candidate->label,
        );
    }

    /**
     * Build from a persisted ambiguity candidate array (the toArray() shape of
     * ResolutionCandidate stored in `ambiguities[].candidates`).
     *
     * @param  array<string, mixed>  $candidate
     */
    public static function fromCandidateArray(array $candidate): self
    {
        return new self(
            id: $candidate['id'],
            type: (string) ($candidate['type'] ?? 'unknown'),
            label: (string) ($candidate['label'] ?? ''),
        );
    }

    /** @return array{id: int|string, type: string, label: string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
        ];
    }
}
