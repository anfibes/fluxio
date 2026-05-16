<?php

namespace Fluxio\Actions\EntityResolution\DTO;

class ResolutionCandidate
{
    public function __construct(
        public readonly int|string $id,
        public readonly string     $type,
        public readonly string     $label,
        public readonly ?string    $description,
        public readonly float      $confidence,
    ) {}

    /** @return array{id: int|string, type: string, label: string, description: string|null, confidence: float} */
    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'type'        => $this->type,
            'label'       => $this->label,
            'description' => $this->description,
            'confidence'  => $this->confidence,
        ];
    }
}
