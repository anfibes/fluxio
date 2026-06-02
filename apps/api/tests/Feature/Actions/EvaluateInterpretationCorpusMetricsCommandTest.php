<?php

namespace Tests\Feature\Actions;

use Fluxio\Actions\Interpretation\Contracts\InterpretationProviderInterface;
use Fluxio\Actions\Interpretation\Providers\DeterministicInterpretationProvider;
use Fluxio\Actions\Llm\Contracts\LlmClientInterface;
use Fluxio\Actions\Llm\DTO\LlmRequest;
use Fluxio\Actions\Llm\DTO\LlmResponse;
use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Feature tests for the Phase 5C metrics / drift flags on
 * actions:evaluate-interpretation-corpus.
 *
 * No real Ollama: LlmClientInterface is faked so the Ollama sandbox provider
 * resolves through the container without HTTP. Provider intent agreement is
 * controlled with single-case temp corpora so --fail-on-drift can be asserted
 * deterministically.
 */
class EvaluateInterpretationCorpusMetricsCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    private function fakeLlmReturning(array $parsedJson): void
    {
        $client = new class($parsedJson) implements LlmClientInterface
        {
            public function __construct(private readonly array $parsedJson) {}

            public function generateStructured(LlmRequest $request): LlmResponse
            {
                return new LlmResponse(
                    rawText: json_encode($this->parsedJson),
                    parsedJson: $this->parsedJson,
                );
            }
        };

        $this->app->bind(LlmClientInterface::class, fn () => $client);
    }

    private function writeCorpus(array $cases): string
    {
        $path = tempnam(sys_get_temp_dir(), 'corpus_').'.json';
        file_put_contents($path, json_encode($cases));
        $this->tempFiles[] = $path;

        return $path;
    }

    /** A single "Create a task" case → deterministic resolves to create_task. */
    private function createTaskCorpus(): string
    {
        return $this->writeCorpus([
            ['id' => 'create-task', 'text' => 'Create a task', 'expected' => ['intent' => 'create_task']],
        ]);
    }

    public function test_metrics_flag_prints_enriched_metrics(): void
    {
        $this->fakeLlmReturning(['intent' => 'create_task', 'confidence' => 0.8, 'entities' => [], 'notes' => []]);

        $exitCode = Artisan::call('actions:evaluate-interpretation-corpus', [
            '--path' => $this->createTaskCorpus(),
            '--metrics' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Metrics', $output);
        $this->assertStringContainsString('provider intent agreement rate', $output);
        $this->assertStringContainsString('Divergent entity keys', $output);
    }

    public function test_json_includes_metrics(): void
    {
        $this->fakeLlmReturning(['intent' => 'create_task', 'confidence' => 0.8, 'entities' => [], 'notes' => []]);

        $exitCode = Artisan::call('actions:evaluate-interpretation-corpus', [
            '--path' => $this->createTaskCorpus(),
            '--json' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        // Backward-friendly: existing summary fields still present.
        $this->assertArrayHasKey('total', $decoded);
        $this->assertArrayHasKey('cases', $decoded);
        // New metrics object.
        $this->assertArrayHasKey('metrics', $decoded);
        $this->assertArrayHasKey('provider_intent_agreement_rate', $decoded['metrics']);
        $this->assertArrayHasKey('divergent_entity_keys', $decoded['metrics']);
        $this->assertEquals(1.0, $decoded['metrics']['provider_intent_agreement_rate']);
    }

    public function test_json_includes_grammar_artifact_from_export(): void
    {
        $this->fakeLlmReturning(['intent' => 'create_task', 'confidence' => 0.8, 'entities' => [], 'notes' => []]);

        Artisan::call('actions:evaluate-interpretation-corpus', [
            '--path' => $this->createTaskCorpus(),
            '--json' => true,
        ]);
        $decoded = json_decode(Artisan::output(), true);

        // Additive grammar block equals the export() artifact — the same provider-facing
        // surface the prompt/validator use; existing summary + metrics fields remain.
        $this->assertArrayHasKey('grammar', $decoded);
        $this->assertSame(
            $this->app->make(\Fluxio\Actions\Interpretation\InterpretationGrammar::class)->export(),
            $decoded['grammar'],
        );
        $this->assertArrayHasKey('total', $decoded);
        $this->assertArrayHasKey('metrics', $decoded);
    }

    public function test_json_includes_baseline_relative_outcomes(): void
    {
        // Deterministic and Ollama both resolve create_task for the create-task case.
        $this->fakeLlmReturning(['intent' => 'create_task', 'confidence' => 0.8, 'entities' => [], 'notes' => []]);

        Artisan::call('actions:evaluate-interpretation-corpus', [
            '--path' => $this->createTaskCorpus(),
            '--json' => true,
        ]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertArrayHasKey('baseline_relative_outcomes', $decoded['metrics']);
        $this->assertArrayHasKey('regression_cases', $decoded['metrics']);
        $this->assertSame(1, $decoded['metrics']['baseline_relative_outcomes']['parity_match']);
        $this->assertSame([], $decoded['metrics']['regression_cases']);
    }

    public function test_json_reports_provider_regression_against_baseline(): void
    {
        // Deterministic → create_task (matches expected); Ollama → schedule_call (regresses).
        $this->fakeLlmReturning(['intent' => 'schedule_call', 'confidence' => 0.5, 'entities' => [], 'notes' => []]);

        Artisan::call('actions:evaluate-interpretation-corpus', [
            '--path' => $this->createTaskCorpus(),
            '--json' => true,
        ]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame(1, $decoded['metrics']['baseline_relative_outcomes']['regressed']);
        $this->assertSame('create-task', $decoded['metrics']['regression_cases'][0]['id']);
        $this->assertSame('create_task', $decoded['metrics']['regression_cases'][0]['expected_intent']);
    }

    public function test_metrics_table_shows_baseline_relative_outcomes(): void
    {
        $this->fakeLlmReturning(['intent' => 'create_task', 'confidence' => 0.8, 'entities' => [], 'notes' => []]);

        Artisan::call('actions:evaluate-interpretation-corpus', [
            '--path' => $this->createTaskCorpus(),
            '--metrics' => true,
        ]);

        $this->assertStringContainsString('Baseline-relative outcomes', Artisan::output());
    }

    public function test_fail_on_drift_exits_success_when_agreement_above_threshold(): void
    {
        // Both providers produce create_task → agreement 1.0 ≥ 0.8.
        $this->fakeLlmReturning(['intent' => 'create_task', 'confidence' => 0.8, 'entities' => [], 'notes' => []]);

        $exitCode = Artisan::call('actions:evaluate-interpretation-corpus', [
            '--path' => $this->createTaskCorpus(),
            '--fail-on-drift' => true,
            '--agreement-threshold' => '0.8',
        ]);

        $this->assertSame(0, $exitCode);
    }

    public function test_fail_on_drift_exits_failure_when_agreement_below_threshold(): void
    {
        // Deterministic → create_task, Ollama → schedule_call → agreement 0.0 < 0.8.
        $this->fakeLlmReturning(['intent' => 'schedule_call', 'confidence' => 0.5, 'entities' => [], 'notes' => []]);

        $exitCode = Artisan::call('actions:evaluate-interpretation-corpus', [
            '--path' => $this->createTaskCorpus(),
            '--fail-on-drift' => true,
            '--agreement-threshold' => '0.8',
        ]);
        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('below the threshold', $output);
    }

    public function test_command_remains_production_blocked(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('actions:evaluate-interpretation-corpus', ['--metrics' => true])
            ->assertFailed()
            ->expectsOutputToContain('disabled in production');

        $this->app['env'] = 'testing';
    }

    public function test_command_does_not_create_action_proposal_records(): void
    {
        $this->fakeLlmReturning(['intent' => 'create_task', 'confidence' => 0.8, 'entities' => [], 'notes' => []]);

        $this->assertSame(0, ActionProposal::count());

        $this->artisan('actions:evaluate-interpretation-corpus', [
            '--path' => $this->createTaskCorpus(),
            '--metrics' => true,
        ])->assertSuccessful();

        $this->assertSame(0, ActionProposal::count());
    }

    public function test_runtime_provider_binding_remains_unchanged(): void
    {
        $this->fakeLlmReturning(['intent' => 'create_task', 'confidence' => 0.8, 'entities' => [], 'notes' => []]);

        $this->artisan('actions:evaluate-interpretation-corpus', [
            '--path' => $this->createTaskCorpus(),
            '--metrics' => true,
        ])->assertSuccessful();

        $this->assertInstanceOf(
            DeterministicInterpretationProvider::class,
            $this->app->make(InterpretationProviderInterface::class),
        );
    }
}
