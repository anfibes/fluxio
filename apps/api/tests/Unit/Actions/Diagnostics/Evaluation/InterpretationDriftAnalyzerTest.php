<?php

namespace Tests\Unit\Actions\Diagnostics\Evaluation;

use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationCorpusCase;
use Fluxio\Actions\Diagnostics\Evaluation\InterpretationDriftAnalyzer;
use Fluxio\Actions\Diagnostics\Evaluation\InterpretationEvaluationService;
use Fluxio\Actions\Diagnostics\InterpretationComparisonService;
use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Interpretation\Contracts\InterpretationProviderInterface;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit tests for the Phase 5C drift analyzer.
 *
 * The analyzer is pure aggregation over an InterpretationEvaluationSummary. To
 * exercise realistic data shapes, summaries are produced through the real
 * InterpretationEvaluationService wired to in-memory provider doubles — no real
 * Ollama, no pipeline, no proposals.
 */
class InterpretationDriftAnalyzerTest extends TestCase
{
    private InterpretationDriftAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new InterpretationDriftAnalyzer;
    }

    /**
     * @param  array<string, mixed>  $entities
     */
    private function commandProvider(string $intent, float $confidence, array $entities = []): InterpretationProviderInterface
    {
        return new class($intent, $confidence, $entities) implements InterpretationProviderInterface
        {
            public function __construct(
                private readonly string $intent,
                private readonly float $confidence,
                private readonly array $entities,
            ) {}

            public function interpret(string $text, InterpretationContext $context): NormalizedCommand
            {
                return new NormalizedCommand(
                    intent: $this->intent,
                    confidence: $this->confidence,
                    sourceText: $text,
                    locale: $context->locale,
                    entities: $this->entities,
                );
            }
        };
    }

    private function throwingProvider(): InterpretationProviderInterface
    {
        return new class implements InterpretationProviderInterface
        {
            public function interpret(string $text, InterpretationContext $context): NormalizedCommand
            {
                throw new RuntimeException('llm down');
            }
        };
    }

    /**
     * @param  array<string, mixed>  $expectedEntities
     */
    private function case(string $id, string $expectedIntent, array $expectedEntities = []): InterpretationCorpusCase
    {
        return new InterpretationCorpusCase(
            id: $id,
            text: "text for {$id}",
            locale: 'en',
            expectedIntent: $expectedIntent,
            expectedEntities: $expectedEntities,
        );
    }

    /**
     * @param  list<InterpretationCorpusCase>  $cases
     */
    private function summaryFor(
        InterpretationProviderInterface $deterministic,
        InterpretationProviderInterface $ollama,
        array $cases,
    ) {
        $service = new InterpretationEvaluationService(
            new InterpretationComparisonService($deterministic, $ollama),
        );

        return $service->evaluate($cases);
    }

    public function test_calculates_provider_success_rates(): void
    {
        $metrics = $this->analyzer->analyze($this->summaryFor(
            $this->commandProvider('create_task', 0.9),
            $this->throwingProvider(),
            [$this->case('a', 'create_task'), $this->case('b', 'create_task')],
        ));

        $this->assertSame(1.0, $metrics->deterministicSuccessRate);
        $this->assertSame(0.0, $metrics->ollamaSuccessRate);
        // 2 failed executions out of 4 total (2 cases x 2 providers).
        $this->assertSame(0.5, $metrics->providerFailureRate);
    }

    public function test_calculates_expected_intent_match_rates(): void
    {
        $metrics = $this->analyzer->analyze($this->summaryFor(
            $this->commandProvider('create_task', 0.9),
            $this->commandProvider('schedule_call', 0.7),
            [$this->case('a', 'create_task'), $this->case('b', 'create_task')],
        ));

        $this->assertSame(1.0, $metrics->deterministicIntentMatchRate);
        $this->assertSame(0.0, $metrics->ollamaIntentMatchRate);
    }

    public function test_calculates_provider_intent_agreement_rate(): void
    {
        // Case a: both create_task (agree). Case b: providers differ (disagree).
        $metrics = $this->analyzer->analyze($this->summaryFor(
            $this->commandProvider('create_task', 0.9),
            $this->commandProvider('create_task', 0.8),
            [$this->case('a', 'create_task')],
        ));

        $this->assertSame(1.0, $metrics->providerIntentAgreementRate);

        $metrics2 = $this->analyzer->analyze($this->summaryFor(
            $this->commandProvider('create_task', 0.9),
            $this->commandProvider('schedule_call', 0.8),
            [$this->case('a', 'create_task'), $this->case('b', 'create_task')],
        ));

        $this->assertSame(0.0, $metrics2->providerIntentAgreementRate);
    }

    public function test_calculates_average_confidence_by_provider(): void
    {
        $metrics = $this->analyzer->analyze($this->summaryFor(
            $this->commandProvider('create_task', 0.9),
            $this->commandProvider('create_task', 0.5),
            [$this->case('a', 'create_task')],
        ));

        $this->assertSame(0.9, $metrics->averageConfidenceByProvider['deterministic']);
        $this->assertSame(0.5, $metrics->averageConfidenceByProvider['ollama']);
        // delta = ollama - deterministic = 0.5 - 0.9 = -0.4
        $this->assertSame(-0.4, $metrics->averageConfidenceDelta);
    }

    public function test_handles_provider_failures_without_division_errors(): void
    {
        // Empty corpus: rates default to 0.0, averages null, no division by zero.
        $metrics = $this->analyzer->analyze($this->summaryFor(
            $this->commandProvider('create_task', 0.9),
            $this->commandProvider('create_task', 0.8),
            [],
        ));

        $this->assertSame(0, $metrics->totalCases);
        $this->assertSame(0.0, $metrics->deterministicSuccessRate);
        $this->assertSame(0.0, $metrics->providerFailureRate);
        $this->assertNull($metrics->averageConfidenceByProvider['deterministic']);
        $this->assertNull($metrics->averageConfidenceByProvider['ollama']);
        $this->assertNull($metrics->averageConfidenceDelta);

        // All-ollama-failure corpus: ollama average is null but no error.
        $failMetrics = $this->analyzer->analyze($this->summaryFor(
            $this->commandProvider('create_task', 0.9),
            $this->throwingProvider(),
            [$this->case('a', 'create_task')],
        ));
        $this->assertNull($failMetrics->averageConfidenceByProvider['ollama']);
        $this->assertNull($failMetrics->averageConfidenceDelta);
    }

    public function test_identifies_intent_mismatch_cases(): void
    {
        $metrics = $this->analyzer->analyze($this->summaryFor(
            $this->commandProvider('create_task', 0.9),
            $this->commandProvider('schedule_call', 0.8),
            [$this->case('mismatch-case', 'create_task')],
        ));

        $this->assertCount(1, $metrics->intentMismatchCases);
        $this->assertSame('mismatch-case', $metrics->intentMismatchCases[0]['id']);
        $this->assertSame('create_task', $metrics->intentMismatchCases[0]['deterministic_intent']);
        $this->assertSame('schedule_call', $metrics->intentMismatchCases[0]['ollama_intent']);
    }

    public function test_identifies_entity_mismatch_cases_and_counts_divergent_keys(): void
    {
        // Deterministic emits lead_query; ollama emits lead — both succeed.
        $metrics = $this->analyzer->analyze($this->summaryFor(
            $this->commandProvider('create_task', 0.9, ['lead_query' => 'Rossi']),
            $this->commandProvider('create_task', 0.8, ['lead' => 'Rossi']),
            [$this->case('a', 'create_task'), $this->case('b', 'create_task')],
        ));

        $this->assertCount(2, $metrics->entityMismatchCases);
        // lead_query (only in deterministic) and lead (only in ollama) each diverge in 2 cases.
        $this->assertSame(2, $metrics->divergentEntityKeys['lead_query']);
        $this->assertSame(2, $metrics->divergentEntityKeys['lead']);
        // Entity agreement rate is 0 (every case diverges).
        $this->assertSame(0.0, $metrics->providerEntityAgreementRate);
    }

    public function test_entity_agreement_when_keys_identical(): void
    {
        $metrics = $this->analyzer->analyze($this->summaryFor(
            $this->commandProvider('create_task', 0.9, ['lead' => 'Rossi']),
            $this->commandProvider('create_task', 0.8, ['lead' => 'Rossi']),
            [$this->case('a', 'create_task')],
        ));

        $this->assertSame([], $metrics->entityMismatchCases);
        $this->assertSame([], $metrics->divergentEntityKeys);
        $this->assertSame(1.0, $metrics->providerEntityAgreementRate);
    }

    public function test_groups_weak_expected_intents(): void
    {
        // create_task: deterministic matches, ollama does not → weak.
        // schedule_call: both match → not weak.
        $metrics = $this->analyzer->analyze($this->summaryFor(
            $this->commandProvider('create_task', 0.9),
            $this->commandProvider('create_task', 0.8),
            [$this->case('a', 'create_task'), $this->case('b', 'schedule_call')],
        ));

        // For case b expected schedule_call: deterministic emits create_task (no match),
        // ollama emits create_task (no match) → schedule_call is weak.
        // For case a expected create_task: both emit create_task → not weak.
        $this->assertArrayHasKey('schedule_call', $metrics->weakExpectedIntents);
        $this->assertArrayNotHasKey('create_task', $metrics->weakExpectedIntents);
        $this->assertSame(0.0, $metrics->weakExpectedIntents['schedule_call']['deterministic_intent_match_rate']);
    }

    // ── Phase 9F.1D: baseline-relative outcomes (provider vs deterministic oracle) ──

    public function test_baseline_parity_match_when_both_hit_expected(): void
    {
        $metrics = $this->analyzer->analyze($this->summaryFor(
            $this->commandProvider('create_task', 0.9),
            $this->commandProvider('create_task', 0.8),
            [$this->case('a', 'create_task')],
        ));

        $this->assertSame(1, $metrics->baselineRelativeOutcomes['parity_match']);
        $this->assertSame(0, $metrics->baselineRelativeOutcomes['regressed']);
        $this->assertSame([], $metrics->regressionCases);
    }

    public function test_baseline_regressed_when_provider_misses_intent_but_deterministic_hits(): void
    {
        $metrics = $this->analyzer->analyze($this->summaryFor(
            $this->commandProvider('create_task', 0.9),
            $this->commandProvider('schedule_call', 0.8),
            [$this->case('reg', 'create_task')],
        ));

        $this->assertSame(1, $metrics->baselineRelativeOutcomes['regressed']);
        $this->assertSame([['id' => 'reg', 'expected_intent' => 'create_task']], $metrics->regressionCases);
    }

    public function test_baseline_regressed_on_entity_mismatch_even_when_intent_agrees(): void
    {
        $metrics = $this->analyzer->analyze($this->summaryFor(
            $this->commandProvider('create_task', 0.9, ['lead' => 'Rossi']),
            $this->commandProvider('create_task', 0.8, ['lead' => 'Wrong']),
            [$this->case('ent', 'create_task', ['lead' => 'Rossi'])],
        ));

        $this->assertSame(1, $metrics->baselineRelativeOutcomes['regressed']);
        $this->assertSame('ent', $metrics->regressionCases[0]['id']);
    }

    public function test_baseline_improved_when_provider_hits_and_deterministic_misses(): void
    {
        $metrics = $this->analyzer->analyze($this->summaryFor(
            $this->commandProvider('schedule_call', 0.9),
            $this->commandProvider('create_task', 0.8),
            [$this->case('imp', 'create_task')],
        ));

        $this->assertSame(1, $metrics->baselineRelativeOutcomes['improved']);
        $this->assertSame([], $metrics->regressionCases);
    }

    public function test_baseline_parity_miss_when_neither_hits_expected(): void
    {
        $metrics = $this->analyzer->analyze($this->summaryFor(
            $this->commandProvider('schedule_call', 0.9),
            $this->commandProvider('schedule_call', 0.8),
            [$this->case('miss', 'create_task')],
        ));

        $this->assertSame(1, $metrics->baselineRelativeOutcomes['parity_miss']);
    }

    public function test_provider_failure_counts_as_baseline_regression_and_stays_visible(): void
    {
        // A failing/sandbox-rejected provider matches nothing → regressed against the
        // deterministic oracle, and the failure remains visible in providerFailureCases.
        $metrics = $this->analyzer->analyze($this->summaryFor(
            $this->commandProvider('create_task', 0.9),
            $this->throwingProvider(),
            [$this->case('fail', 'create_task')],
        ));

        $this->assertSame(1, $metrics->baselineRelativeOutcomes['regressed']);
        $this->assertSame('fail', $metrics->regressionCases[0]['id']);
        $this->assertCount(1, $metrics->providerFailureCases);
    }
}
