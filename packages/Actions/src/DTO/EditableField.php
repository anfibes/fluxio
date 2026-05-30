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
        public readonly ?EditableFieldExplanation $explanation = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'key' => $this->key,
            'label' => $this->label,
            'value' => $this->value,
            'source' => $this->source,
            'required' => $this->required,
        ];

        // Only emit the optional explanation when present — never an empty object.
        if ($this->explanation !== null) {
            $data['explanation'] = $this->explanation->toArray();
        }

        return $data;
    }
}
