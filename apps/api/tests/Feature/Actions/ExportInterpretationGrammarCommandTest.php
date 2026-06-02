<?php

namespace Tests\Feature\Actions;

use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Interpretation\Contracts\InterpretationProviderInterface;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;
use Fluxio\Actions\Interpretation\InterpretationGrammar;
use Fluxio\Actions\Llm\Contracts\LlmClientInterface;
use Fluxio\Actions\Llm\DTO\LlmRequest;
use Fluxio\Actions\Llm\DTO\LlmResponse;
use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase 9F.2B — diagnostics command that prints the exported InterpretationGrammar
 * artifact. Read-only: no provider, no LLM, no network, no persistence, blocked in
 * production, and outside the deterministic CI gate.
 */
class ExportInterpretationGrammarCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_outputs_valid_pretty_json(): void
    {
        $this->assertSame(0, Artisan::call('actions:export-interpretation-grammar'));

        $decoded = json_decode(Artisan::output(), true);

        $this->assertIsArray($decoded);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }

    public function test_output_equals_grammar_export(): void
    {
        Artisan::call('actions:export-interpretation-grammar');

        $decoded = json_decode(Artisan::output(), true);
        $expected = $this->app->make(InterpretationGrammar::class)->export();

        $this->assertSame($expected, $decoded);
    }

    public function test_compact_flag_emits_single_line_json_with_same_content(): void
    {
        Artisan::call('actions:export-interpretation-grammar', ['--compact' => true]);
        $output = trim(Artisan::output());

        // Compact JSON has no newlines inside the document.
        $this->assertStringNotContainsString("\n", $output);
        $this->assertSame(
            $this->app->make(InterpretationGrammar::class)->export(),
            json_decode($output, true),
        );
    }

    public function test_command_is_blocked_in_production(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('actions:export-interpretation-grammar')
            ->assertFailed()
            ->expectsOutputToContain('disabled in production');

        $this->app['env'] = 'testing';
    }

    public function test_command_creates_no_action_proposal_records(): void
    {
        $this->assertSame(0, ActionProposal::count());

        $this->artisan('actions:export-interpretation-grammar')->assertSuccessful();

        $this->assertSame(0, ActionProposal::count());
    }

    public function test_command_calls_no_provider_llm_or_network(): void
    {
        // Any HTTP call would throw; the command must make none.
        Http::preventStrayRequests();

        // Resolving/calling a provider or the LLM client would throw; the command must
        // touch neither — it only reads the InterpretationGrammar projection.
        $this->app->bind(InterpretationProviderInterface::class, fn () => new class implements InterpretationProviderInterface
        {
            public function interpret(string $text, InterpretationContext $context): NormalizedCommand
            {
                throw new RuntimeException('provider must not be called by the export command');
            }
        });

        $this->app->bind(LlmClientInterface::class, fn () => new class implements LlmClientInterface
        {
            public function generateStructured(LlmRequest $request): LlmResponse
            {
                throw new RuntimeException('LLM client must not be called by the export command');
            }
        });

        $this->assertSame(0, Artisan::call('actions:export-interpretation-grammar'));
        $this->assertNotEmpty(json_decode(Artisan::output(), true));
    }

    public function test_command_is_not_part_of_the_deterministic_ci_gate(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $gate = implode("\n", (array) ($composer['scripts']['test:actions-corpora'] ?? []));

        $this->assertStringNotContainsString('actions:export-interpretation-grammar', $gate);
    }
}
