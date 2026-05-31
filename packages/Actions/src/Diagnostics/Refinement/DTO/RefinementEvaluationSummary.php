<?php

namespace Fluxio\Actions\Diagnostics\Refinement\DTO;

/**
 * Aggregate result of evaluating the whole refinement corpus. Counts are
 * diagnostics metrics only; nothing here grants any provider authority or
 * changes runtime behavior. The corpus is an executable specification of the
 * refinement behavior Fluxio supports today and must keep supporting.
 */
final class RefinementEvaluationSummary
{
    /**
     * @param  list<RefinementCaseResult>  $cases
     */
    public function __construct(
        public readonly int $total,
        public readonly int $passedCount,
        public readonly int $failedCount,
        public readonly array $cases,
    ) {}

    public function allPassed(): bool
    {
        return $this->failedCount === 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'passed_count' => $this->passedCount,
            'failed_count' => $this->failedCount,
            'cases' => array_map(
                static fn (RefinementCaseResult $case): array => $case->toArray(),
                $this->cases,
            ),
        ];
    }
}
