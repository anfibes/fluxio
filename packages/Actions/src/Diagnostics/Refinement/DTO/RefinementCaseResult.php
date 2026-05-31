<?php

namespace Fluxio\Actions\Diagnostics\Refinement\DTO;

/**
 * Result of evaluating a single refinement corpus case against the actual
 * rule-based refinement output. Diagnostics-only: it records whether the
 * observed behavior matched the expectations and, when it did not, the explicit
 * mismatch reasons. `actual` is a small snapshot of what the refinement produced
 * (semantic types, changed fields, status, warnings, ambiguity state) so a
 * failing case is debuggable without re-running.
 */
final class RefinementCaseResult
{
    /**
     * @param  list<string>  $failures
     * @param  array<string, mixed>  $actual
     */
    public function __construct(
        public readonly string $id,
        public readonly string $description,
        public readonly bool $passed,
        public readonly array $failures,
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
            'actual' => $this->actual,
        ];
    }
}
