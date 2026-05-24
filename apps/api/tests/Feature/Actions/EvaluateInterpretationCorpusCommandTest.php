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
}
