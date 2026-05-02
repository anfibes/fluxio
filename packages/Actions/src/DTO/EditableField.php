<?php

namespace Fluxio\Actions\DTO;

class EditableField
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly mixed $value,
        public readonly string $source,
        public readonly bool $required = true,
    ) {}

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'value' => $this->value,
            'source' => $this->source,
            'required' => $this->required,
        ];
    }
}
