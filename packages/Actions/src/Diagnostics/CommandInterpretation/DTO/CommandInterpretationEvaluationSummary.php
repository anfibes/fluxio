<?php

namespace Fluxio\Actions\Diagnostics\CommandInterpretation\DTO;

/**
 * Aggregate result of evaluating the whole command-interpretation corpus.
 *
 * Counts and per-dimension accuracy are diagnostics metrics only; nothing here
 * grants any provider authority or changes runtime behavior. The corpus is an
 * executable specification of the proposal-level interpretation behavior Fluxio
 * produces today, and the baseline a future provider must be compared against.
 *
 * `metrics` is keyed by dimension (intent | status | entity | ambiguity); each is
 * `{ checked, matched }` where checked counts the cases that asserted that
 * dimension. Accuracy is matched / checked (1.0 when nothing asserted it).
 */
final class CommandInterpretationEvaluationSummary
{
    /**
     * @param  list<CommandInterpretationCaseResult>  $cases
     * @param  array<string, array{checked: int, matched: int}>  $metrics
     */
    public function __construct(
        public readonly int $total,
        public readonly int $passedCount,
        public readonly int $failedCount,
        public readonly array $cases,
        public readonly array $metrics,
    ) {}

    public function allPassed(): bool
    {
        return $this->failedCount === 0;
    }

    public function accuracy(string $dimension): float
    {
        $checked = $this->metrics[$dimension]['checked'] ?? 0;
        $matched = $this->metrics[$dimension]['matched'] ?? 0;

        return $checked === 0 ? 1.0 : $matched / $checked;
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
            'metrics' => $this->metrics,
            'cases' => array_map(
                static fn (CommandInterpretationCaseResult $case): array => $case->toArray(),
                $this->cases,
            ),
        ];
    }
}
