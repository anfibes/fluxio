<?php

namespace Fluxio\Actions\DTO;

class ActionProposal
{
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
            'missing' => $this->missing,
            'warnings' => $this->warnings,
            'editable_fields' => $this->editable_fields,
            'changes' => $this->changes,
            'needs_confirmation' => $this->needs_confirmation,
        ];
    }
}
