<?php

namespace Fluxio\Actions\Diagnostics\Evaluation\DTO;

/**
 * Lightweight, development-only metrics derived from an
 * InterpretationEvaluationSummary by InterpretationDriftAnalyzer.
 *
 * Pure aggregation of existing comparison data — no providers are run, no
 * proposals are built, nothing is persisted, and no provider output becomes
 * authoritative. Rates are simple ratios; confidence averages are null when no
 * provider execution succeeded. Entity analysis stays exact (no fuzzy/semantic
 * matching, no entity resolution): the deterministic `lead_query` vs canonical
 * `lead` divergence is intentionally left visible.
 *
 * `providerFailureRate` is over provider executions (2 per case: deterministic
 * + ollama), mirroring InterpretationEvaluationSummary::$providerFailureCount.
 */
final class InterpretationEvaluationMetrics
{
    /**
     * @param  array{deterministic: float|null, ollama: float|null}  $averageConfidenceByProvider
     * @param  list<array<string, mixed>>  $highestConfidenceDeltaCases
     * @param  list<array<string, mixed>>  $intentMismatchCases
     * @param  list<array<string, mixed>>  $entityMismatchCases
     * @param  list<array<string, mixed>>  $providerFailureCases
     * @param  array<string, array<string, mixed>>  $weakExpectedIntents
     * @param  array<string, int>  $divergentEntityKeys
     * @param  array{improved: int, regressed: int, parity_match: int, parity_miss: int}  $baselineRelativeOutcomes
     * @param  list<array{id: string, expected_intent: string}>  $regressionCases
     */
    public function __construct(
        public readonly int $totalCases,
        public readonly float $deterministicSuccessRate,
        public readonly float $ollamaSuccessRate,
        public readonly float $deterministicIntentMatchRate,
        public readonly float $ollamaIntentMatchRate,
        public readonly float $deterministicEntityMatchRate,
        public readonly float $ollamaEntityMatchRate,
        public readonly float $providerIntentAgreementRate,
        public readonly float $providerEntityAgreementRate,
        public readonly float $providerFailureRate,
        public readonly array $averageConfidenceByProvider,
        public readonly ?float $averageConfidenceDelta,
        public readonly array $highestConfidenceDeltaCases,
        public readonly array $intentMismatchCases,
        public readonly array $entityMismatchCases,
        public readonly array $providerFailureCases,
        public readonly array $weakExpectedIntents,
        public readonly array $divergentEntityKeys,
        public readonly array $baselineRelativeOutcomes,
        public readonly array $regressionCases,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_cases' => $this->totalCases,
            'deterministic_success_rate' => $this->deterministicSuccessRate,
            'ollama_success_rate' => $this->ollamaSuccessRate,
            'deterministic_intent_match_rate' => $this->deterministicIntentMatchRate,
            'ollama_intent_match_rate' => $this->ollamaIntentMatchRate,
            'deterministic_entity_match_rate' => $this->deterministicEntityMatchRate,
            'ollama_entity_match_rate' => $this->ollamaEntityMatchRate,
            'provider_intent_agreement_rate' => $this->providerIntentAgreementRate,
            'provider_entity_agreement_rate' => $this->providerEntityAgreementRate,
            'provider_failure_rate' => $this->providerFailureRate,
            'average_confidence_by_provider' => $this->averageConfidenceByProvider,
            'average_confidence_delta' => $this->averageConfidenceDelta,
            'highest_confidence_delta_cases' => $this->highestConfidenceDeltaCases,
            'intent_mismatch_cases' => $this->intentMismatchCases,
            'entity_mismatch_cases' => $this->entityMismatchCases,
            'provider_failure_cases' => $this->providerFailureCases,
            'weak_expected_intents' => $this->weakExpectedIntents,
            'divergent_entity_keys' => $this->divergentEntityKeys,
            'baseline_relative_outcomes' => $this->baselineRelativeOutcomes,
            'regression_cases' => $this->regressionCases,
        ];
    }
}
