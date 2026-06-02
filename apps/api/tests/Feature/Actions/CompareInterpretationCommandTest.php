<?php

namespace Tests\Feature\Actions;

use Fluxio\Actions\Interpretation\Contracts\InterpretationProviderInterface;
use Fluxio\Actions\Interpretation\InterpretationGrammar;
use Fluxio\Actions\Interpretation\Providers\DeterministicInterpretationProvider;
use Fluxio\Actions\Llm\Contracts\LlmClientInterface;
use Fluxio\Actions\Llm\DTO\LlmRequest;
use Fluxio\Actions\Llm\DTO\LlmResponse;
use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Feature tests for the Phase 5A diagnostics command.
 *
 * No real Ollama: LlmClientInterface is faked so the Ollama sandbox provider
 * resolves through the container without HTTP. The command must never build a
 * proposal, must be blocked in production, and must leave the configured
 * runtime provider untouched.
 */
class CompareInterpretationCommandTest extends TestCase
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

    public function test_command_outputs_diagnostic_data_and_does_not_change_runtime_provider(): void
    {
        $this->fakeLlmReturning([
            'intent' => 'create_task',
            'confidence' => 0.82,
            'entities' => [],
            'notes' => [],
        ]);

        $exitCode = Artisan::call('actions:compare-interpretation', [
            'text' => 'Create a follow-up task for Rossi tomorrow at 10am',
            '--json' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"deterministic"', $output);
        $this->assertStringContainsString('"ollama"', $output);
        $this->assertStringContainsString('"same_intent"', $output);

        // Runtime binding is still the default deterministic provider.
        $this->assertInstanceOf(
            DeterministicInterpretationProvider::class,
            $this->app->make(InterpretationProviderInterface::class),
        );
    }

    public function test_json_output_includes_grammar_artifact_from_export(): void
    {
        $this->fakeLlmReturning([
            'intent' => 'create_task',
            'confidence' => 0.82,
            'entities' => [],
            'notes' => [],
        ]);

        Artisan::call('actions:compare-interpretation', [
            'text' => 'Create a task for Rossi',
            '--json' => true,
        ]);
        $decoded = json_decode(Artisan::output(), true);

        // The grammar block is additive and is the exact export() artifact — proving the
        // comparison was inspected against the same provider-facing surface the prompt
        // builder and validator use.
        $this->assertArrayHasKey('grammar', $decoded);
        $this->assertSame(
            $this->app->make(InterpretationGrammar::class)->export(),
            $decoded['grammar'],
        );

        // Existing fields remain (backward-friendly).
        $this->assertArrayHasKey('deterministic', $decoded);
        $this->assertArrayHasKey('same_intent', $decoded);
    }

    public function test_command_does_not_create_action_proposal_records(): void
    {
        $this->fakeLlmReturning([
            'intent' => 'create_task',
            'confidence' => 0.82,
            'entities' => [],
            'notes' => [],
        ]);

        $this->assertSame(0, ActionProposal::count());

        $this->artisan('actions:compare-interpretation', [
            'text' => 'Create a task for Rossi',
            '--json' => true,
        ])->assertSuccessful();

        $this->assertSame(0, ActionProposal::count());
    }

    public function test_command_is_blocked_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('actions:compare-interpretation', [
            'text' => 'Create a task',
        ])
            ->assertFailed()
            ->expectsOutputToContain('disabled in production');

        $this->app['env'] = 'testing';
    }
}
