<?php

namespace Fluxio\Actions\Diagnostics\Evaluation\DTO;

/**
 * Aggregate result of evaluating the whole corpus. Counts are diagnostics
 * metrics only; nothing here grants the LLM authority or changes runtime
 * behaviour.
 *
 * `providerFailureCount` counts failed provider executions across all cases
 * (max 2 per case: deterministic + ollama), not cases-with-any-failure.
 */
final class InterpretationEvaluationSummary
{
    /**
     * @param  list<InterpretationEvaluationResult>  $cases
     */
    public function __construct(
        public readonly int $total,
        public readonly int $deterministicSuccessCount,
        public readonly int $ollamaSuccessCount,
        public readonly int $deterministicIntentMatchCount,
        public readonly int $ollamaIntentMatchCount,
        public readonly int $deterministicEntityMatchCount,
        public readonly int $ollamaEntityMatchCount,
        public readonly int $intentMismatchCountBetweenProviders,
        public readonly int $providerFailureCount,
        public readonly array $cases,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'deterministic_success_count' => $this->deterministicSuccessCount,
            'ollama_success_count' => $this->ollamaSuccessCount,
            'deterministic_intent_match_count' => $this->deterministicIntentMatchCount,
            'ollama_intent_match_count' => $this->ollamaIntentMatchCount,
            'deterministic_entity_match_count' => $this->deterministicEntityMatchCount,
            'ollama_entity_match_count' => $this->ollamaEntityMatchCount,
            'intent_mismatch_count_between_providers' => $this->intentMismatchCountBetweenProviders,
            'provider_failure_count' => $this->providerFailureCount,
            'cases' => array_map(
                static fn (InterpretationEvaluationResult $case): array => $case->toArray(),
                $this->cases,
            ),
        ];
    }
}
