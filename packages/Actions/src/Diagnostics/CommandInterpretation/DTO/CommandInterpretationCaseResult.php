<?php

namespace Fluxio\Actions\Diagnostics\CommandInterpretation\DTO;

/**
 * Result of evaluating a single command-interpretation corpus case against the
 * actual deterministic interpretation output. Diagnostics-only.
 *
 * `dimensions` records, per checked fidelity dimension (intent, status, entity,
 * ambiguity), whether it matched — used to aggregate per-dimension accuracy.
 * Dimensions not expected by the case are absent (not counted). `actual` is a
 * small snapshot for debugging a failing case without re-running.
 */
final class CommandInterpretationCaseResult
{
    /**
     * @param  list<string>  $failures
     * @param  array<string, bool>  $dimensions
     * @param  array<string, mixed>  $actual
     */
    public function __construct(
        public readonly string $id,
        public readonly string $description,
        public readonly bool $passed,
        public readonly array $failures,
        public readonly array $dimensions,
        public readonly array $actual,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'passed' => $this->passed,
            'failures' => $this->failures,
            'dimensions' => $this->dimensions,
            'actual' => $this->actual,
        ];
    }
}
