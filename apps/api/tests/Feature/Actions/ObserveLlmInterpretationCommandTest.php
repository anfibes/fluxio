<?php

namespace Tests\Feature\Actions;

use Fluxio\Actions\Llm\Contracts\LlmClientInterface;
use Fluxio\Actions\Llm\DTO\LlmRequest;
use Fluxio\Actions\Llm\DTO\LlmResponse;
use Fluxio\Actions\Llm\Exceptions\LlmTransportException;
use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Phase 9F.3B — the opt-in LLM observation command. No real Ollama: LlmClientInterface
 * is faked so the observation pipeline runs deterministically in tests. The command must
 * build no proposals, stay production-blocked, and remain outside the deterministic gate.
 */
class ObserveLlmInterpretationCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, mixed>|null $parsedJson */
    private function fakeLlmReturning(?array $parsedJson): void
    {
        $client = new class($parsedJson) implements LlmClientInterface
        {
            public function __construct(private readonly ?array $parsedJson) {}

            public function generateStructured(LlmRequest $request): LlmResponse
            {
                return new LlmResponse(
                    rawText: $this->parsedJson === null ? 'not json' : json_encode($this->parsedJson),
                    parsedJson: $this->parsedJson,
                );
            }
        };

        $this->app->bind(LlmClientInterface::class, fn () => $client);
    }

    private function fakeLlmThrowing(\Throwable $e): void
    {
        $client = new class($e) implements LlmClientInterface
        {
            public function __construct(private readonly \Throwable $e) {}

            public function generateStructured(LlmRequest $request): LlmResponse
            {
                throw $this->e;
            }
        };

        $this->app->bind(LlmClientInterface::class, fn () => $client);
    }

    private function validCreateTask(): array
    {
        return ['intent' => 'create_task', 'confidence' => 0.8, 'entities' => ['lead' => 'Mario Rossi'], 'notes' => []];
    }

    /** @return array<string, mixed> */
    private function runJson(array $options = []): array
    {
        Artisan::call('actions:observe-llm-interpretation', $options + ['--json' => true]);

        return json_decode(Artisan::output(), true);
    }

    // ── Diagnostics-only guarantees ───────────────────────────────────────────

    public function test_command_is_blocked_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('actions:observe-llm-interpretation')
            ->assertFailed()
            ->expectsOutputToContain('disabled in production');

        $this->app['env'] = 'testing';
    }

    public function test_command_creates_no_action_proposal_records(): void
    {
        $this->fakeLlmReturning($this->validCreateTask());

        $this->assertSame(0, ActionProposal::count());

        $this->artisan('actions:observe-llm-interpretation')->assertSuccessful();

        $this->assertSame(0, ActionProposal::count());
    }

    public function test_command_is_not_part_of_the_deterministic_ci_gate(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $gate = implode("\n", (array) ($composer['scripts']['test:actions-corpora'] ?? []));

        $this->assertStringNotContainsString('actions:observe-llm-interpretation', $gate);
    }

    // ── Observation output ────────────────────────────────────────────────────

    public function test_grammar_metadata_is_present(): void
    {
        $this->fakeLlmReturning($this->validCreateTask());

        $decoded = $this->runJson(['--limit' => 1]);

        $this->assertSame('interpretation.provider-grammar', $decoded['grammar']['contract']);
        $this->assertSame(1, $decoded['grammar']['version']);
    }

    public function test_valid_provider_output_is_reported_with_captured_stages(): void
    {
        $this->fakeLlmReturning($this->validCreateTask());

        $case = $this->runJson(['--case' => 'create-task-lead-temporal'])['cases'][0];

        $this->assertTrue($case['provider']['succeeded']);
        $this->assertTrue($case['structured_output']['valid']);
        $this->assertSame('create_task', $case['normalized_command']['intent']);
        $this->assertSame(['lead' => 'Mario Rossi'], $case['normalized_command']['entities']);
        // Captured raw + parsed stages, and the adapter-equivalent outcome (sandbox-legal here).
        $this->assertSame($this->validCreateTask(), $case['parsed_json']);
        $this->assertSame([], $case['adapter_outcome']['sandbox_violations']);
        $this->assertTrue($case['match']['intent']);
    }

    public function test_malformed_provider_output_is_reported_as_invalid(): void
    {
        // Unregistered intent — fails the structured-output contract, no command produced.
        $this->fakeLlmReturning(['intent' => 'hallucinated_action', 'confidence' => 0.9, 'entities' => [], 'notes' => []]);

        $case = $this->runJson(['--case' => 'create-task-lead-temporal'])['cases'][0];

        $this->assertTrue($case['provider']['succeeded']);
        $this->assertFalse($case['structured_output']['valid']);
        $this->assertNotEmpty($case['structured_output']['errors']);
        $this->assertNull($case['normalized_command']);
    }

    public function test_provider_transport_failure_is_reported_as_failure(): void
    {
        $this->fakeLlmThrowing(new LlmTransportException('Ollama returned HTTP 500.'));

        $case = $this->runJson(['--case' => 'create-task-lead-temporal'])['cases'][0];

        $this->assertFalse($case['provider']['succeeded']);
        $this->assertSame(LlmTransportException::class, $case['provider']['failure_class']);
        $this->assertStringContainsString('HTTP 500', $case['provider']['failure_message']);
    }

    public function test_case_and_limit_filters(): void
    {
        $this->fakeLlmReturning($this->validCreateTask());

        $single = $this->runJson(['--case' => 'assign-lead']);
        $this->assertCount(1, $single['cases']);
        $this->assertSame('assign-lead', $single['cases'][0]['id']);

        $limited = $this->runJson(['--limit' => 2]);
        $this->assertCount(2, $limited['cases']);

        Artisan::call('actions:observe-llm-interpretation', ['--case' => 'does-not-exist']);
        $this->assertStringContainsString('No observation case with id [does-not-exist]', Artisan::output());
    }
}
