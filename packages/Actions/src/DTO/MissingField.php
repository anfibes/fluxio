<?php

namespace Fluxio\Actions\DTO;

class MissingField
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?string $reason = null,
        public readonly bool $required = true,
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'reason' => $this->reason,
            'required' => $this->required,
        ];
    }
}
