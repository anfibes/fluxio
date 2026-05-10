<?php

namespace Fluxio\Actions\DTO;

class NormalizedMutation
{
    /**
     * A single field mutation produced by a refinement interpreter.
     *
     * @param array<string, mixed> $metadata  Interpreter-specific extra data
     */
    public function __construct(
        public readonly string $field,
        public readonly string $label,
        public readonly mixed  $value,
        public readonly string $source     = 'detected',
        public readonly float  $confidence = 1.0,
        public readonly array  $metadata   = [],
    ) {}
}
