<?php

namespace Fluxio\Actions\Diagnostics\Evaluation;

use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationEvaluationMetrics;
use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationEvaluationResult;
use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationEvaluationSummary;

/**
 * Computes lightweight metrics and drift diagnostics from an
 * InterpretationEvaluationSummary.
 *
 * Diagnostics-only and purely derivational: it consumes already-computed
 * comparison data, never runs providers, knows nothing about Ollama transport,
 * builds no proposals, touches no ActionInterpreterService, persists nothing,
 * and adds no runtime behaviour. All formulas are simple, explicit ratios over
 * exact match data — no fuzzy or semantic scoring.
 */
class InterpretationDriftAnalyzer
{
    /** How many top |confidence delta| cases to surface. */
    private const TOP_DELTA_CASES = 3;

    public function analyze(InterpretationEvaluationSummary $summary): InterpretationEvaluationMetrics
    {
        $total = $summary->total;

        $intentMismatchCases = [];
        $entityMismatchCases = [];
        $providerFailureCases = [];
        $divergentEntityKeys = [];
        $deltaCases = [];

        $detConfidences = [];
        $ollamaConfidences = [];
        $deltas = [];

        $entityAgreementCount = 0;

        /** @var array<string, array{cases: int, deterministic_intent_match: int, ollama_intent_match: int}> $byExpectedIntent */
        $byExpectedIntent = [];

        foreach ($summary->cases as $case) {
            $comparison = $case->comparison;
            $det = $comparison->deterministic;
            $ollama = $comparison->ollama;

            // Per-expected-intent grouping.
            $expected = $case->expectedIntent;
            $byExpectedIntent[$expected] ??= ['cases' => 0, 'deterministic_intent_match' => 0, 'ollama_intent_match' => 0];
            $byExpectedIntent[$expected]['cases']++;
            $byExpectedIntent[$expected]['deterministic_intent_match'] += $case->deterministicIntentMatch ? 1 : 0;
            $byExpectedIntent[$expected]['ollama_intent_match'] += $case->ollamaIntentMatch ? 1 : 0;

            // Confidence aggregates (successful executions only).
            if ($det->success && $det->confidence !== null) {
                $detConfidences[] = $det->confidence;
            }
            if ($ollama->success && $ollama->confidence !== null) {
                $ollamaConfidences[] = $ollama->confidence;
            }
            if ($comparison->confidenceDelta !== null) {
                $deltas[] = $comparison->confidenceDelta;
                $deltaCases[] = $this->deltaCaseRow($case);
            }

            // Intent mismatch between providers (only meaningful when both succeeded).
            if ($comparison->sameIntent === false) {
                $intentMismatchCases[] = [
                    'id' => $case->id,
                    'expected_intent' => $case->expectedIntent,
                    'deterministic_intent' => $det->intent,
                    'ollama_intent' => $ollama->intent,
                ];
            }

            // Entity divergence (only computed by the comparison service when both succeeded).
            $divergentKeysForCase = $this->divergentKeys($comparison->entityDiff);
            if ($comparison->bothSucceeded) {
                if ($divergentKeysForCase === []) {
                    $entityAgreementCount++;
                } else {
                    $entityMismatchCases[] = [
                        'id' => $case->id,
                        'divergent_keys' => $divergentKeysForCase,
                    ];
                    foreach ($divergentKeysForCase as $key) {
                        $divergentEntityKeys[$key] = ($divergentEntityKeys[$key] ?? 0) + 1;
                    }
                }
            }

            // Provider failures.
            if (! $det->success || ! $ollama->success) {
                $providerFailureCases[] = [
                    'id' => $case->id,
                    'deterministic_error' => $det->errorClass,
                    'ollama_error' => $ollama->errorClass,
                ];
            }
        }

        // Top cases by absolute confidence delta.
        usort($deltaCases, static fn (array $a, array $b): int => abs($b['confidence_delta']) <=> abs($a['confidence_delta']));
        $highestConfidenceDeltaCases = array_slice($deltaCases, 0, self::TOP_DELTA_CASES);

        return new InterpretationEvaluationMetrics(
            totalCases: $total,
            deterministicSuccessRate: $this->rate($summary->deterministicSuccessCount, $total),
            ollamaSuccessRate: $this->rate($summary->ollamaSuccessCount, $total),
            deterministicIntentMatchRate: $this->rate($summary->deterministicIntentMatchCount, $total),
            ollamaIntentMatchRate: $this->rate($summary->ollamaIntentMatchCount, $total),
            deterministicEntityMatchRate: $this->rate($summary->deterministicEntityMatchCount, $total),
            ollamaEntityMatchRate: $this->rate($summary->ollamaEntityMatchCount, $total),
            // Agreement = providers succeeded AND produced the same intent, over all cases.
            providerIntentAgreementRate: $this->rate($this->countAgreingIntents($summary), $total),
            providerEntityAgreementRate: $this->rate($entityAgreementCount, $total),
            // Failure rate over provider executions (2 per case).
            providerFailureRate: $this->rate($summary->providerFailureCount, $total * 2),
            averageConfidenceByProvider: [
                'deterministic' => $this->average($detConfidences),
                'ollama' => $this->average($ollamaConfidences),
            ],
            averageConfidenceDelta: $this->average($deltas),
            highestConfidenceDeltaCases: $highestConfidenceDeltaCases,
            intentMismatchCases: $intentMismatchCases,
            entityMismatchCases: $entityMismatchCases,
            providerFailureCases: $providerFailureCases,
            weakExpectedIntents: $this->weakExpectedIntents($byExpectedIntent),
            divergentEntityKeys: $divergentEntityKeys,
        );
    }

    private function countAgreingIntents(InterpretationEvaluationSummary $summary): int
    {
        $count = 0;
        foreach ($summary->cases as $case) {
            if ($case->comparison->sameIntent === true) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    private function deltaCaseRow(InterpretationEvaluationResult $case): array
    {
        return [
            'id' => $case->id,
            'confidence_delta' => $case->comparison->confidenceDelta,
            'deterministic_intent' => $case->comparison->deterministic->intent,
            'ollama_intent' => $case->comparison->ollama->intent,
        ];
    }

    /**
     * Keys that diverge between providers for one case, from the comparison's
     * entity_diff (only_in_deterministic + only_in_ollama + different_values).
     *
     * @param  array<string, mixed>  $entityDiff
     * @return list<string>
     */
    private function divergentKeys(array $entityDiff): array
    {
        $keys = array_merge(
            array_keys($entityDiff['only_in_deterministic'] ?? []),
            array_keys($entityDiff['only_in_ollama'] ?? []),
            array_keys($entityDiff['different_values'] ?? []),
        );

        return array_values(array_unique(array_map('strval', $keys)));
    }

    /**
     * Expected intents that are not perfectly matched by both providers.
     *
     * @param  array<string, array{cases: int, deterministic_intent_match: int, ollama_intent_match: int}>  $byExpectedIntent
     * @return array<string, array<string, mixed>>
     */
    private function weakExpectedIntents(array $byExpectedIntent): array
    {
        $weak = [];
        foreach ($byExpectedIntent as $intent => $stats) {
            $detRate = $this->rate($stats['deterministic_intent_match'], $stats['cases']);
            $ollamaRate = $this->rate($stats['ollama_intent_match'], $stats['cases']);

            if ($detRate < 1.0 || $ollamaRate < 1.0) {
                $weak[$intent] = [
                    'cases' => $stats['cases'],
                    'deterministic_intent_match' => $stats['deterministic_intent_match'],
                    'ollama_intent_match' => $stats['ollama_intent_match'],
                    'deterministic_intent_match_rate' => $detRate,
                    'ollama_intent_match_rate' => $ollamaRate,
                ];
            }
        }

        return $weak;
    }

    private function rate(int $numerator, int $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round($numerator / $denominator, 4);
    }

    /**
     * @param  list<float>  $values
     */
    private function average(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        return round(array_sum($values) / count($values), 4);
    }
}
