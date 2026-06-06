<?php

namespace Tests\Unit\Actions\Diagnostics;

use Carbon\Carbon;
use Fluxio\Actions\Diagnostics\InterpretationComparisonService;
use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Interpretation\Contracts\InterpretationProviderInterface;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;
use RuntimeException;
use Tests\TestCase;

/**
 * Unit tests for the Phase 5A diagnostics comparison service.
 *
 * Providers are in-memory doubles — no real Ollama, no proposal pipeline, no
 * entity resolution, no ActionInterpreterService. The service is exercised in
 * isolation against fixed NormalizedCommand fixtures / thrown exceptions.
 */
class InterpretationComparisonServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-05-10 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $entities
     * @param  list<string>  $warnings
     */
    private function commandProvider(
        string $intent,
        float $confidence,
        array $entities = [],
        array $warnings = [],
    ): InterpretationProviderInterface {
        return new class($intent, $confidence, $entities, $warnings) implements InterpretationProviderInterface
        {
            public function __construct(
                private readonly string $intent,
                private readonly float $confidence,
                private readonly array $entities,
                private readonly array $warnings,
            ) {}

            public function interpret(string $text, InterpretationContext $context): NormalizedCommand
            {
                return new NormalizedCommand(
                    intent: $this->intent,
                    confidence: $this->confidence,
                    sourceText: $text,
                    locale: $context->locale,
                    entities: $this->entities,
                    warnings: $this->warnings,
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

    public function test_comparison_succeeds_when_both_providers_return_valid_command(): void
    {
        $service = new InterpretationComparisonService(
            $this->commandProvider('create_task', 0.9),
            $this->commandProvider('create_task', 0.8),
        );

        $result = $service->compare('Create a task');

        $this->assertTrue($result->bothSucceeded);
        $this->assertFalse($result->anyFailed);
        $this->assertTrue($result->deterministic->success);
        $this->assertTrue($result->ollama->success);
    }

    public function test_same_intent_is_detected(): void
    {
        $service = new InterpretationComparisonService(
            $this->commandProvider('create_task', 0.9),
            $this->commandProvider('create_task', 0.7),
        );

        $result = $service->compare('Create a task');

        $this->assertTrue($result->sameIntent);
        $this->assertContains('intent match', $result->notes);
    }

    public function test_intent_mismatch_is_detected(): void
    {
        $service = new InterpretationComparisonService(
            $this->commandProvider('create_task', 0.9),
            $this->commandProvider('schedule_call', 0.7),
        );

        $result = $service->compare('Do something');

        $this->assertFalse($result->sameIntent);
        $this->assertContains('intent mismatch', $result->notes);
    }

    public function test_confidence_delta_is_calculated(): void
    {
        $service = new InterpretationComparisonService(
            $this->commandProvider('create_task', 0.9),
            $this->commandProvider('create_task', 0.7),
        );

        $result = $service->compare('Create a task');

        // ollama - deterministic = 0.7 - 0.9 = -0.2
        $this->assertEqualsWithDelta(-0.2, $result->confidenceDelta, 0.0000001);
    }

    public function test_entity_diff_detects_same_values(): void
    {
        $service = new InterpretationComparisonService(
            $this->commandProvider('create_task', 0.9, ['lead' => 'Rossi']),
            $this->commandProvider('create_task', 0.8, ['lead' => 'Rossi']),
        );

        $result = $service->compare('Create a task for Rossi');

        $this->assertSame(['lead' => 'Rossi'], $result->entityDiff['same_values']);
        $this->assertSame([], $result->entityDiff['different_values']);
    }

    public function test_entity_diff_detects_only_in_deterministic(): void
    {
        $service = new InterpretationComparisonService(
            $this->commandProvider('create_task', 0.9, ['lead' => 'Rossi', 'priority' => 'high']),
            $this->commandProvider('create_task', 0.8, ['lead' => 'Rossi']),
        );

        $result = $service->compare('Create a task');

        $this->assertSame(['priority' => 'high'], $result->entityDiff['only_in_deterministic']);
        $this->assertSame([], $result->entityDiff['only_in_ollama']);
    }

    public function test_entity_diff_detects_only_in_ollama(): void
    {
        $service = new InterpretationComparisonService(
            $this->commandProvider('create_task', 0.9, ['lead' => 'Rossi']),
            $this->commandProvider('create_task', 0.8, ['lead' => 'Rossi', 'due_at' => 'tomorrow']),
        );

        $result = $service->compare('Create a task');

        $this->assertSame(['due_at' => 'tomorrow'], $result->entityDiff['only_in_ollama']);
        $this->assertSame([], $result->entityDiff['only_in_deterministic']);
    }

    public function test_entity_diff_detects_different_values_for_same_key(): void
    {
        $service = new InterpretationComparisonService(
            $this->commandProvider('create_task', 0.9, ['lead' => 'Rossi']),
            $this->commandProvider('create_task', 0.8, ['lead' => 'Rossini']),
        );

        $result = $service->compare('Create a task');

        $this->assertSame(
            ['lead' => ['deterministic' => 'Rossi', 'ollama' => 'Rossini']],
            $result->entityDiff['different_values'],
        );
    }

    public function test_deterministic_failure_is_captured_as_provider_result(): void
    {
        $service = new InterpretationComparisonService(
            $this->throwingProvider(new RuntimeException('boom')),
            $this->commandProvider('create_task', 0.8),
        );

        $result = $service->compare('Create a task');

        $this->assertFalse($result->deterministic->success);
        $this->assertSame(RuntimeException::class, $result->deterministic->errorClass);
        $this->assertSame('boom', $result->deterministic->errorMessage);
        $this->assertTrue($result->anyFailed);
        $this->assertFalse($result->bothSucceeded);
        $this->assertNull($result->sameIntent);
        $this->assertNull($result->confidenceDelta);
        $this->assertTrue($result->ollama->success);
    }

    public function test_provider_durations_are_captured(): void
    {
        $service = new InterpretationComparisonService(
            $this->commandProvider('create_task', 0.9),
            $this->commandProvider('create_task', 0.8),
        );

        $result = $service->compare('Create a task');

        // hrtime-based timing is captured per provider (>= 0) and is unaffected by Carbon::setTestNow.
        $this->assertNotNull($result->deterministic->durationMs);
        $this->assertNotNull($result->ollama->durationMs);
        $this->assertGreaterThanOrEqual(0.0, $result->deterministic->durationMs);
        $this->assertGreaterThanOrEqual(0.0, $result->ollama->durationMs);

        // Per-case total is the sum of the two provider durations.
        $this->assertEqualsWithDelta(
            $result->deterministic->durationMs + $result->ollama->durationMs,
            $result->caseDurationMs,
            0.01,
        );
    }

    public function test_duration_is_captured_even_when_a_provider_fails(): void
    {
        $service = new InterpretationComparisonService(
            $this->commandProvider('create_task', 0.9),
            $this->throwingProvider(new RuntimeException('llm down')),
        );

        $result = $service->compare('Create a task');

        $this->assertFalse($result->ollama->success);
        $this->assertNotNull($result->ollama->durationMs);
        $this->assertGreaterThanOrEqual(0.0, $result->ollama->durationMs);
    }

    public function test_ollama_failure_is_captured_as_provider_result(): void
    {
        $service = new InterpretationComparisonService(
            $this->commandProvider('create_task', 0.9),
            $this->throwingProvider(new RuntimeException('llm down')),
        );

        $result = $service->compare('Create a task');

        $this->assertTrue($result->deterministic->success);
        $this->assertFalse($result->ollama->success);
        $this->assertSame(RuntimeException::class, $result->ollama->errorClass);
        $this->assertSame('llm down', $result->ollama->errorMessage);
        $this->assertTrue($result->anyFailed);
        $this->assertContains('ollama provider failed: '.RuntimeException::class, $result->notes);
    }
}
