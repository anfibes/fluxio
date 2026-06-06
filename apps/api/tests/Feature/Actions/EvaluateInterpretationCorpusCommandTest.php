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
 * Feature tests for the Phase 5B corpus evaluation command.
 *
 * No real Ollama: LlmClientInterface is faked so the Ollama sandbox provider
 * resolves through the container without HTTP. The command must never build a
 * proposal, must be blocked in production, and must leave the configured
 * runtime provider untouched. The shipped corpus is used.
 */
class EvaluateInterpretationCorpusCommandTest extends TestCase
{
    use RefreshDatabase;

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

    private function fakeValidLlm(): void
    {
        // A contract-valid structured output for create_task; the validator
        // accepts it for any case, so the Ollama provider always succeeds.
        $this->fakeLlmReturning([
            'intent' => 'create_task',
            'confidence' => 0.8,
            'entities' => [],
            'notes' => [],
        ]);
    }

    public function test_command_outputs_json(): void
    {
        $this->fakeValidLlm();

        $exitCode = Artisan::call('actions:evaluate-interpretation-corpus', ['--json' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"total"', $output);
        $this->assertStringContainsString('"cases"', $output);
        $this->assertStringContainsString('"deterministic_intent_match_count"', $output);

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertGreaterThan(0, $decoded['total']);
        $this->assertCount($decoded['total'], $decoded['cases']);
    }

    public function test_command_does_not_create_action_proposal_records(): void
    {
        $this->fakeValidLlm();

        $this->assertSame(0, ActionProposal::count());

        $this->artisan('actions:evaluate-interpretation-corpus', ['--json' => true])
            ->assertSuccessful();

        $this->assertSame(0, ActionProposal::count());
    }

    public function test_command_does_not_change_runtime_provider_binding(): void
    {
        $this->fakeValidLlm();

        $this->artisan('actions:evaluate-interpretation-corpus', ['--json' => true])
            ->assertSuccessful();

        $this->assertInstanceOf(
            DeterministicInterpretationProvider::class,
            $this->app->make(InterpretationProviderInterface::class),
        );
    }

    public function test_command_is_blocked_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('actions:evaluate-interpretation-corpus')
            ->assertFailed()
            ->expectsOutputToContain('disabled in production');

        $this->app['env'] = 'testing';
    }

    // ── --case / --limit filtering ────────────────────────────────────────────

    public function test_case_filter_returns_only_the_selected_case(): void
    {
        $this->fakeValidLlm();

        Artisan::call('actions:evaluate-interpretation-corpus', [
            '--case' => 'schedule-meeting-two-people',
            '--json' => true,
        ]);
        $decoded = json_decode(Artisan::output(), true);

        // Same JSON shape, narrowed to one case; metrics + grammar still present.
        $this->assertSame(1, $decoded['total']);
        $this->assertCount(1, $decoded['cases']);
        $this->assertSame('schedule-meeting-two-people', $decoded['cases'][0]['id']);
        $this->assertSame(1, $decoded['metrics']['total_cases']);
        $this->assertArrayHasKey('grammar', $decoded);
    }

    public function test_case_filter_only_evaluates_the_single_case(): void
    {
        // A counting client proves only ONE provider call happens (not 50).
        $client = new class implements LlmClientInterface
        {
            public int $calls = 0;

            public function generateStructured(LlmRequest $request): LlmResponse
            {
                $this->calls++;

                return new LlmResponse(
                    rawText: json_encode(['intent' => 'create_task', 'confidence' => 0.8, 'entities' => [], 'notes' => []]),
                    parsedJson: ['intent' => 'create_task', 'confidence' => 0.8, 'entities' => [], 'notes' => []],
                );
            }
        };
        $this->app->bind(LlmClientInterface::class, fn () => $client);

        $exit = Artisan::call('actions:evaluate-interpretation-corpus', [
            '--case' => 'schedule-meeting-two-people',
            '--metrics' => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame(1, $client->calls);
    }

    public function test_case_filter_with_unknown_id_fails_with_clear_error(): void
    {
        $this->fakeValidLlm();

        $this->artisan('actions:evaluate-interpretation-corpus', ['--case' => 'does-not-exist'])
            ->assertFailed()
            ->expectsOutputToContain('No corpus case with id [does-not-exist].');
    }

    public function test_limit_returns_only_the_first_n_cases(): void
    {
        $this->fakeValidLlm();

        Artisan::call('actions:evaluate-interpretation-corpus', [
            '--limit' => 3,
            '--json' => true,
        ]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame(3, $decoded['total']);
        $this->assertCount(3, $decoded['cases']);
        $this->assertSame(3, $decoded['metrics']['total_cases']);
    }

    public function test_limit_greater_than_corpus_evaluates_all_cases(): void
    {
        $this->fakeValidLlm();

        Artisan::call('actions:evaluate-interpretation-corpus', [
            '--limit' => 100000,
            '--json' => true,
        ]);
        $decoded = json_decode(Artisan::output(), true);

        // Slice past the end just yields the whole corpus (> 0, never an error).
        $this->assertGreaterThan(3, $decoded['total']);
        $this->assertCount($decoded['total'], $decoded['cases']);
    }

    public function test_limit_zero_fails_with_clear_error(): void
    {
        $this->fakeValidLlm();

        $this->artisan('actions:evaluate-interpretation-corpus', ['--limit' => 0])
            ->assertFailed()
            ->expectsOutputToContain('--limit must be a positive integer.');
    }

    public function test_case_and_limit_together_fails(): void
    {
        $this->fakeValidLlm();

        $this->artisan('actions:evaluate-interpretation-corpus', [
            '--case' => 'schedule-meeting-two-people',
            '--limit' => 2,
        ])
            ->assertFailed()
            ->expectsOutputToContain('--case and --limit cannot be used together.');
    }

    public function test_json_output_includes_timing_fields_additively(): void
    {
        $this->fakeValidLlm();

        Artisan::call('actions:evaluate-interpretation-corpus', ['--json' => true]);
        $decoded = json_decode(Artisan::output(), true);

        $this->assertIsArray($decoded);

        // Top-level evaluation duration (additive — existing keys still present).
        $this->assertArrayHasKey('total_duration_ms', $decoded);
        $this->assertArrayHasKey('total', $decoded);
        $this->assertIsNumeric($decoded['total_duration_ms']);
        $this->assertGreaterThanOrEqual(0, $decoded['total_duration_ms']);

        // Per-case + per-provider durations.
        $case = $decoded['cases'][0];
        $this->assertArrayHasKey('duration_ms', $case['comparison']);
        $this->assertArrayHasKey('duration_ms', $case['comparison']['deterministic']);
        $this->assertArrayHasKey('duration_ms', $case['comparison']['ollama']);
        $this->assertGreaterThanOrEqual(0, $case['comparison']['deterministic']['duration_ms']);

        // Aggregate timing metrics grouped under metrics.timing.
        $timing = $decoded['metrics']['timing'];
        $this->assertArrayHasKey('total_duration_ms', $timing);
        $this->assertArrayHasKey('average_deterministic_duration_ms', $timing);
        $this->assertArrayHasKey('average_ollama_duration_ms', $timing);
        $this->assertArrayHasKey('max_ollama_duration_ms', $timing);
        $this->assertArrayHasKey('slowest_ollama_cases', $timing);
        $this->assertIsArray($timing['slowest_ollama_cases']);
    }

    public function test_progress_flag_does_not_corrupt_json_stdout(): void
    {
        $this->fakeValidLlm();

        // --json together with --progress: progress must go to stderr only, so stdout
        // stays a single valid JSON document that json_decode can parse cleanly.
        $exitCode = Artisan::call('actions:evaluate-interpretation-corpus', [
            '--json' => true,
            '--progress' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);

        $decoded = json_decode($output, true);
        $this->assertSame(JSON_ERROR_NONE, json_last_error(), 'stdout is not pure JSON: '.$output);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('total', $decoded);
    }
}
