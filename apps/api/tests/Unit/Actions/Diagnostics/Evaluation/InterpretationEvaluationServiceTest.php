<?php

namespace Tests\Unit\Actions\Diagnostics\Evaluation;

use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationCorpusCase;
use Fluxio\Actions\Diagnostics\Evaluation\InterpretationEvaluationService;
use Fluxio\Actions\Diagnostics\InterpretationComparisonService;
use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Interpretation\Contracts\InterpretationProviderInterface;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit tests for the Phase 5B evaluation service.
 *
 * Providers are in-memory doubles — no real Ollama, no proposal pipeline, no
 * entity resolution, no ActionInterpreterService. The evaluation service is
 * exercised through a real InterpretationComparisonService wired to the doubles.
 */
class InterpretationEvaluationServiceTest extends TestCase
{
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

    private function throwingProvider(\Throwable $e): InterpretationProviderInterface
    {
        return new class($e) implements InterpretationProviderInterface
        {
            public function __construct(private readonly \Throwable $e) {}

            public function interpret(string $text, InterpretationContext $context): NormalizedCommand
            {
                throw $this->e;
            }
        };
    }

    private function service(
        InterpretationProviderInterface $deterministic,
        InterpretationProviderInterface $ollama,
    ): InterpretationEvaluationService {
        return new InterpretationEvaluationService(
            new InterpretationComparisonService($deterministic, $ollama),
        );
    }

    /**
     * @param  array<string, mixed>  $expectedEntities
     */
    private function case(string $id, string $text, string $expectedIntent, array $expectedEntities = []): InterpretationCorpusCase
    {
        return new InterpretationCorpusCase(
            id: $id,
            text: $text,
            locale: 'en',
            expectedIntent: $expectedIntent,
            expectedEntities: $expectedEntities,
        );
    }

    public function test_summary_counts_total_cases(): void
    {
        $service = $this->service(
            $this->commandProvider('create_task', 0.9),
            $this->commandProvider('create_task', 0.8),
        );

        $summary = $service->evaluate([
            $this->case('a', 'Create a task', 'create_task'),
            $this->case('b', 'Create another task', 'create_task'),
        ]);

        $this->assertSame(2, $summary->total);
        $this->assertCount(2, $summary->cases);
    }

    public function test_deterministic_expected_intent_match_is_counted(): void
    {
        $service = $this->service(
            $this->commandProvider('create_task', 0.9),
            $this->commandProvider('schedule_call', 0.8),
        );

        $summary = $service->evaluate([
            $this->case('a', 'Create a task', 'create_task'),
        ]);

        $this->assertSame(1, $summary->deterministicIntentMatchCount);
        $this->assertSame(0, $summary->ollamaIntentMatchCount);
        $this->assertTrue($summary->cases[0]->deterministicIntentMatch);
        $this->assertFalse($summary->cases[0]->ollamaIntentMatch);
    }

    public function test_ollama_expected_intent_match_is_counted(): void
    {
        $service = $this->service(
            $this->commandProvider('schedule_call', 0.9),
            $this->commandProvider('create_task', 0.8),
        );

        $summary = $service->evaluate([
            $this->case('a', 'Create a task', 'create_task'),
        ]);

        $this->assertSame(0, $summary->deterministicIntentMatchCount);
        $this->assertSame(1, $summary->ollamaIntentMatchCount);
        $this->assertSame(1, $summary->intentMismatchCountBetweenProviders);
    }

    public function test_partial_entity_match_works(): void
    {
        // Expected subset {lead: Rossi}. Deterministic emits it plus extras → match.
        // Ollama emits a different value for the same key → no match.
        $service = $this->service(
            $this->commandProvider('create_task', 0.9, ['lead' => 'Rossi', 'due_at' => 'tomorrow']),
            $this->commandProvider('create_task', 0.8, ['lead' => 'Rossini']),
        );

        $summary = $service->evaluate([
            $this->case('a', 'Create a task for Rossi', 'create_task', ['lead' => 'Rossi']),
        ]);

        $this->assertSame(1, $summary->deterministicEntityMatchCount);
        $this->assertSame(0, $summary->ollamaEntityMatchCount);
        $this->assertTrue($summary->cases[0]->deterministicEntityMatch);
        $this->assertFalse($summary->cases[0]->ollamaEntityMatch);
    }

    public function test_missing_expected_entity_key_does_not_match(): void
    {
        $service = $this->service(
            $this->commandProvider('create_task', 0.9, ['priority' => 'high']),
            $this->commandProvider('create_task', 0.8, ['lead' => 'Rossi']),
        );

        $summary = $service->evaluate([
            $this->case('a', 'Create a task for Rossi', 'create_task', ['lead' => 'Rossi']),
        ]);

        $this->assertFalse($summary->cases[0]->deterministicEntityMatch);
        $this->assertTrue($summary->cases[0]->ollamaEntityMatch);
    }

    public function test_provider_failure_is_counted_but_does_not_abort_evaluation(): void
    {
        // Ollama throws for every case; deterministic succeeds. The loop must
        // still complete all cases and count the failures.
        $service = $this->service(
            $this->commandProvider('create_task', 0.9),
            $this->throwingProvider(new RuntimeException('llm down')),
        );

        $summary = $service->evaluate([
            $this->case('a', 'Create a task', 'create_task'),
            $this->case('b', 'Create another task', 'create_task'),
        ]);

        $this->assertSame(2, $summary->total);
        $this->assertCount(2, $summary->cases);
        $this->assertSame(2, $summary->deterministicSuccessCount);
        $this->assertSame(0, $summary->ollamaSuccessCount);
        $this->assertSame(2, $summary->providerFailureCount);
        $this->assertFalse($summary->cases[0]->comparison->ollama->success);
        $this->assertSame(RuntimeException::class, $summary->cases[0]->comparison->ollama->errorClass);
    }
}
