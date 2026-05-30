<?php

namespace Fluxio\Actions\DTO;

/**
 * Deterministic, proposal-scoped operational context derived from a single
 * ActionProposal (Phase 7A foundation).
 *
 * IMPORTANT — what this is and is NOT:
 *  - It IS a read-only snapshot of the current proposal's operational state,
 *    derived deterministically from data already stored on the proposal. It
 *    exists only to support proposal-scoped refinement semantics.
 *  - It is NOT chat history, conversational memory, or assistant memory. It is
 *    NOT persisted separately, never queries the database, never resolves
 *    ambiguities, never parses natural language, and never mutates proposals.
 *
 * All derivation happens in ProposalRuntimeContextFactory; this DTO only holds
 * the snapshot and exposes small read helpers over it.
 */
class ProposalRuntimeContext
{
    /**
     * @param  array<string, mixed>  $entities  Stored proposal entities (key => value)
     * @param  array<int, array<string, mixed>>  $editableFields  Stored editable fields (each: key/label/value/source/required[/explanation])
     * @param  array<int, array<string, mixed>>  $missingFields  Stored missing fields (each: key/label/required)
     * @param  array<int, array<string, mixed>>  $ambiguities  Stored ambiguities
     * @param  list<string>  $warnings  Stored informational warnings
     * @param  array<string, mixed>|null  $lastRefinement  Stored last_refinement payload, if any
     * @param  array<string, array<int, mixed>>  $collections  Derived collection-like fields (key => list), e.g. participants
     * @param  array<string, mixed>  $temporal  Derived temporal values (e.g. date / time)
     * @param  array<int, array<string, mixed>>  $blockingAmbiguities  Ambiguities where blocking === true
     */
    public function __construct(
        public readonly string $proposalId,
        public readonly string $intent,
        public readonly string $status,
        public readonly array $entities = [],
        public readonly array $editableFields = [],
        public readonly array $missingFields = [],
        public readonly array $ambiguities = [],
        public readonly array $warnings = [],
        public readonly ?array $lastRefinement = null,
        public readonly array $collections = [],
        public readonly array $temporal = [],
        public readonly array $blockingAmbiguities = [],
    ) {}

    /** Current value of an editable field, or null when the field is absent. */
    public function fieldValue(string $key): mixed
    {
        foreach ($this->editableFields as $field) {
            if (($field['key'] ?? null) === $key) {
                return $field['value'] ?? null;
            }
        }

        return null;
    }

    /** True when an editable field with the given key exists. */
    public function hasField(string $key): bool
    {
        foreach ($this->editableFields as $field) {
            if (($field['key'] ?? null) === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * Keys of all currently missing fields.
     *
     * @return list<string>
     */
    public function missingKeys(): array
    {
        return array_values(array_filter(array_map(
            fn (array $field) => $field['key'] ?? null,
            $this->missingFields,
        ), fn ($key) => $key !== null));
    }

    /** True when at least one blocking ambiguity is present. */
    public function hasBlockingAmbiguities(): bool
    {
        return $this->blockingAmbiguities !== [];
    }

    /**
     * Derived collection values for a key (e.g. participants), or [] when absent.
     *
     * @return array<int, mixed>
     */
    public function collection(string $key): array
    {
        return $this->collections[$key] ?? [];
    }

    /** Derived temporal value for a key (e.g. 'date' / 'time'), or null when absent. */
    public function temporalValue(string $key): mixed
    {
        return $this->temporal[$key] ?? null;
    }
}
