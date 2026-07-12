<?php

namespace Fluxio\Actions\DTO;

class ActionProposalData
{
    /**
     * `resolved_entities` is the server-owned identity map keyed by operational
     * role (lead/assignee/task). It is always an array for proposals built by the
     * runtime — the persisted [] (vs null) is what marks a proposal as built under
     * the identity-continuity contract.
     *
     * @param  MissingField[]  $missing
     * @param  EditableField[]  $editable_fields
     * @param  ProposedChange[]  $changes
     * @param  array<string, ResolvedEntity>  $resolved_entities
     */
    public function __construct(
        public readonly string $id,
        public readonly string $intent,
        public readonly string $status,
        public readonly float $confidence,
        public readonly string $source_text,
        public readonly array $entities = [],
        public readonly array $missing = [],
        public readonly array $warnings = [],
        public readonly array $editable_fields = [],
        public readonly array $changes = [],
        public readonly bool $needs_confirmation = true,
        public readonly array $ambiguities = [],
        public readonly array $resolved_entities = [],
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'intent' => $this->intent,
            'status' => $this->status,
            'confidence' => $this->confidence,
            'source_text' => $this->source_text,
            'entities' => $this->entities,
            'missing' => array_map(fn (MissingField $f) => $f->toArray(), $this->missing),
            'warnings' => $this->warnings,
            'editable_fields' => array_map(fn (EditableField $f) => $f->toArray(), $this->editable_fields),
            'changes' => array_map(fn (ProposedChange $c) => $c->toArray(), $this->changes),
            'needs_confirmation' => $this->needs_confirmation,
            'ambiguities' => $this->ambiguities,
            'resolved_entities' => array_map(fn (ResolvedEntity $e) => $e->toArray(), $this->resolved_entities),
        ];
    }
}
