<?php

namespace Fluxio\Actions\DTO;

class ProposedChange
{
    public function __construct(
        public readonly string $type,
        public readonly string $label,
        public readonly string $module,
        public readonly array $payload = [],
    ) {}

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'label' => $this->label,
            'module' => $this->module,
            'payload' => $this->payload,
        ];
    }
}
