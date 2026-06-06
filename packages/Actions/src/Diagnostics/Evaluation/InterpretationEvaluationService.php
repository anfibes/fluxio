<?php

namespace Fluxio\Actions\Diagnostics\Evaluation;

use Fluxio\Actions\Diagnostics\DTO\InterpretationProviderResult;
use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationCorpusCase;
use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationEvaluationResult;
use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationEvaluationSummary;
use Fluxio\Actions\Diagnostics\InterpretationComparisonService;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;

/**
 * Evaluates a corpus of known commands through the Phase 5A diagnostics
 * comparison service and aggregates how each provider matches the expected
 * intent/entities.
 *
 * Diagnostics-only and built entirely on InterpretationComparisonService: not
 * runtime hybrid mode, builds no proposals, calls no ActionInterpreterService,
 * resolves no entities, persists nothing, and leaves the configured runtime
 * provider untouched. A captured provider failure is counted but never aborts
 * the run; only an invalid corpus (in the loader) aborts.
 *
 * Entity matching is exact and partial: a provider matches a case when, for
 * every key in the case's expected entities, the provider emitted the same key
 * with an identical (===) value. Empty expected entities match vacuously when
 * the provider succeeded. No fuzzy or semantic comparison.
 */
class InterpretationEvaluationService
{
    public function __construct(
        private readonly InterpretationComparisonService $comparison,
    ) {}

    /**
     * @param  list<InterpretationCorpusCase>  $cases
     * @param  (callable(int $completed, int $total): void)|null  $onProgress  Per-case progress hook (diagnostics UX only): invoked after each case with the count completed and the total; never affects evaluation outcomes.
     */
    public function evaluate(array $cases, ?callable $onProgress = null): InterpretationEvaluationSummary
    {
        // Monotonic wall-clock for the whole corpus pass (diagnostics observability).
        $startedAt = hrtime(true);

        $total = count($cases);
        $completed = 0;

        $results = [];

        $deterministicSuccess = 0;
        $ollamaSuccess = 0;
        $deterministicIntentMatch = 0;
        $ollamaIntentMatch = 0;
        $deterministicEntityMatch = 0;
        $ollamaEntityMatch = 0;
        $intentMismatchBetween = 0;
        $providerFailures = 0;

        foreach ($cases as $case) {
            $comparison = $this->comparison->compare(
                $case->text,
                new InterpretationContext(locale: $case->locale),
            );

            $det = $comparison->deterministic;
            $ollama = $comparison->ollama;

            $detIntentMatch = $this->intentMatches($det, $case->expectedIntent);
            $ollamaIntentMatchD = $this->intentMatches($ollama, $case->expectedIntent);
            $detEntityMatch = $this->entitiesMatch($det, $case->expectedEntities);
            $ollamaEntityMatchD = $this->entitiesMatch($ollama, $case->expectedEntities);

            $deterministicSuccess += $det->success ? 1 : 0;
            $ollamaSuccess += $ollama->success ? 1 : 0;
            $deterministicIntentMatch += $detIntentMatch ? 1 : 0;
            $ollamaIntentMatch += $ollamaIntentMatchD ? 1 : 0;
            $deterministicEntityMatch += $detEntityMatch ? 1 : 0;
            $ollamaEntityMatch += $ollamaEntityMatchD ? 1 : 0;
            $providerFailures += ($det->success ? 0 : 1) + ($ollama->success ? 0 : 1);

            if ($comparison->sameIntent === false) {
                $intentMismatchBetween++;
            }

            $results[] = new InterpretationEvaluationResult(
                id: $case->id,
                text: $case->text,
                locale: $case->locale,
                expectedIntent: $case->expectedIntent,
                expectedEntities: $case->expectedEntities,
                comparison: $comparison,
                deterministicIntentMatch: $detIntentMatch,
                ollamaIntentMatch: $ollamaIntentMatchD,
                deterministicEntityMatch: $detEntityMatch,
                ollamaEntityMatch: $ollamaEntityMatchD,
            );

            $completed++;
            if ($onProgress !== null) {
                $onProgress($completed, $total);
            }
        }

        return new InterpretationEvaluationSummary(
            total: count($cases),
            deterministicSuccessCount: $deterministicSuccess,
            ollamaSuccessCount: $ollamaSuccess,
            deterministicIntentMatchCount: $deterministicIntentMatch,
            ollamaIntentMatchCount: $ollamaIntentMatch,
            deterministicEntityMatchCount: $deterministicEntityMatch,
            ollamaEntityMatchCount: $ollamaEntityMatch,
            intentMismatchCountBetweenProviders: $intentMismatchBetween,
            providerFailureCount: $providerFailures,
            cases: $results,
            totalDurationMs: round((hrtime(true) - $startedAt) / 1_000_000, 3),
        );
    }

    private function intentMatches(InterpretationProviderResult $result, string $expectedIntent): bool
    {
        return $result->success && $result->intent === $expectedIntent;
    }

    /**
     * @param  array<string, mixed>  $expectedEntities
     */
    private function entitiesMatch(InterpretationProviderResult $result, array $expectedEntities): bool
    {
        if (! $result->success) {
            return false;
        }

        foreach ($expectedEntities as $key => $value) {
            if (! array_key_exists($key, $result->entities) || $result->entities[$key] !== $value) {
                return false;
            }
        }

        return true;
    }
}
