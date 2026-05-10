<?php

namespace Fluxio\Actions\DTO;

class NormalizedMutation
{
    /**
     * A single mutation produced by a refinement interpreter.
     *
     * @param string               $operation  Semantic operation: replace | clear | append
     * @param string               $field      Target field key (date, time, priority, …)
     * @param string               $label      Human-readable field label
     * @param mixed                $value      New value for replace; null for clear
     * @param string               $source     Origin of the mutation (detected, inferred, …)
     * @param float                $confidence Interpreter confidence [0–1]
     * @param array<string, mixed> $metadata   Interpreter-specific extra data
     */
    public function __construct(
        public readonly string $field,
        public readonly string $label,
        public readonly mixed  $value,
        public readonly string $operation  = 'replace',
        public readonly string $source     = 'detected',
        public readonly float  $confidence = 1.0,
        public readonly array  $metadata   = [],
    ) {}
}
