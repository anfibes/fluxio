<?php

namespace Fluxio\Actions\Diagnostics\Examples;

use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationCorpusCase;
use Fluxio\Actions\Diagnostics\Examples\DTO\IntentExampleObservationMetrics;
use Fluxio\Actions\Diagnostics\Examples\DTO\IntentExampleObservationReport;
use Fluxio\Actions\Diagnostics\Observation\DTO\LlmObservationResult;
use Fluxio\Actions\Diagnostics\Observation\LlmObservationService;
use Fluxio\Actions\Examples\IntentExample;
use Fluxio\Actions\Examples\IntentExampleRegistry;

/**
 * Diagnostics-only baseline observation of literal Intent Examples through the existing
 * LLM sandbox (Slice A1).
 *
 * This is the FIRST consumer of IntentExampleRegistry, and it lives entirely in the
 * diagnostics namespace — never in the resolver, interpretation runtime, lifecycle, or
 * executors. It takes the curated literal Intent Examples for a locale and replays each one
 * through the unchanged LlmObservationService (same client, prompt builder, structured-output
 * validator and sandbox the opt-in Ollama provider uses), so we can measure — before any
 * prompt change or few-shot injection — whether the current sandbox turns a (e.g. Italian)
 * command into a contract-valid NormalizedCommand.
 *
 * Boundaries held: it builds no proposals, resolves no entities, executes nothing, persists
 * nothing, and adds no relaxed/parallel validator. IntentExamples stay non-authoritative; the
 * deterministic provider remains the runtime authority. Template-only examples are deliberately
 * excluded as evaluated inputs in this slice — only records carrying literal text are run.
 */
class IntentExampleObservationService
{
    public function __construct(
        private readonly IntentExampleRegistry $registry,
        private readonly LlmObservationService $observation,
    ) {}

    /**
     * Select the literal examples to observe for a locale, in registry order, with optional
     * id and count filters. Template-only examples are excluded.
     *
     * @return list<IntentExample>
     */
    public function select(string $locale, ?string $caseId = null, ?int $limit = null): array
    {
        $examples = array_values(array_filter(
            $this->registry->byLocale($locale),
            static fn (IntentExample $e): bool => $e->hasLiteral(),
        ));

        if ($caseId !== null) {
            $examples = array_values(array_filter(
                $examples,
                static fn (IntentExample $e): bool => $e->id === $caseId,
            ));
        }

        if ($limit !== null) {
            $examples = array_slice($examples, 0, max(0, $limit));
        }

        return $examples;
    }

    /**
     * Replay a single literal example through the unchanged LLM observation pipeline.
     *
     * The example is adapted to the shared InterpretationCorpusCase shape: its literal `text`
     * is the input, its `intent` the expectation. Literal Intent Examples carry no expected
     * entities, so the entity expectation is empty (entity agreement is reported as n/a).
     */
    public function observeExample(IntentExample $example): LlmObservationResult
    {
        $case = new InterpretationCorpusCase(
            id: $example->id,
            text: (string) $example->text,
            locale: $example->locale,
            expectedIntent: $example->intent,
            expectedEntities: [],
            notes: $example->notes,
        );

        return $this->observation->observe($case);
    }

    /**
     * @param  list<LlmObservationResult>  $results
     */
    public function report(string $locale, array $results): IntentExampleObservationReport
    {
        return new IntentExampleObservationReport(
            locale: $locale,
            results: $results,
            metrics: IntentExampleObservationMetrics::fromResults($results),
        );
    }

    /**
     * Convenience: select + observe + aggregate in one call (used by tests).
     */
    public function observe(string $locale = 'it', ?string $caseId = null, ?int $limit = null): IntentExampleObservationReport
    {
        $results = array_map(
            fn (IntentExample $e): LlmObservationResult => $this->observeExample($e),
            $this->select($locale, $caseId, $limit),
        );

        return $this->report($locale, $results);
    }
}
