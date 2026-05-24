<?php

namespace Tests\Unit\Actions\Interpretation;

use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Exceptions\InvalidNormalizedCommandException;
use Fluxio\Actions\Interpretation\Contracts\InterpretationProviderInterface;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;
use Fluxio\Actions\Interpretation\InterpretationProviderAdapter;
use Fluxio\Actions\Interpretation\Providers\OllamaInterpretationProvider;
use Fluxio\Actions\Llm\Contracts\LlmClientInterface;
use Fluxio\Actions\Llm\DTO\LlmRequest;
use Fluxio\Actions\Llm\DTO\LlmResponse;
use Fluxio\Actions\Llm\Exceptions\InvalidLlmStructuredOutputException;
use Fluxio\Actions\Llm\Prompting\InterpretationPromptBuilder;
use Fluxio\Actions\Llm\Validation\LlmStructuredOutputValidator;
use Fluxio\Actions\Validation\NormalizedCommandValidator;
use Tests\TestCase;

/**
 * Unit tests for the opt-in Ollama interpretation sandbox provider.
 *
 * No real Ollama is contacted: LlmClientInterface is replaced by an in-memory
 * stub that returns a fixed LlmResponse. The proposal lifecycle, entity
 * resolution, and execution are out of scope here — these tests cover only the
 * provider boundary (prompt → client → validator → NormalizedCommand) and the
 * adapter validation path.
 */
class OllamaInterpretationProviderTest extends TestCase
{
    private function stubClient(?array $parsedJson): LlmClientInterface
    {
        return new class($parsedJson) implements LlmClientInterface
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
    }

    private function provider(?array $parsedJson): OllamaInterpretationProvider
    {
        return new OllamaInterpretationProvider(
            client: $this->stubClient($parsedJson),
            validator: $this->app->make(LlmStructuredOutputValidator::class),
            promptBuilder: $this->app->make(InterpretationPromptBuilder::class),
        );
    }

    private function context(string $locale = 'en'): InterpretationContext
    {
        return new InterpretationContext(locale: $locale);
    }

    // ── Happy path ───────────────────────────────────────────────────────────

    public function test_valid_structured_output_produces_normalized_command(): void
    {
        $command = $this->provider([
            'intent' => 'create_task',
            'confidence' => 0.82,
            'entities' => ['lead' => 'Rossi', 'due_at' => 'tomorrow'],
            'notes' => [],
        ])->interpret('Create a task for Rossi tomorrow', $this->context());

        $this->assertInstanceOf(NormalizedCommand::class, $command);
        $this->assertSame('create_task', $command->intent);
        $this->assertSame(0.82, $command->confidence);
        $this->assertSame(['lead' => 'Rossi', 'due_at' => 'tomorrow'], $command->entities);
    }

    public function test_notes_are_mapped_to_warnings(): void
    {
        $command = $this->provider([
            'intent' => 'create_task',
            'confidence' => 0.7,
            'entities' => [],
            'notes' => ['assumed task module'],
        ])->interpret('Create a task', $this->context());

        $this->assertSame(['assumed task module'], $command->warnings);
    }

    public function test_provider_preserves_source_text(): void
    {
        $text = 'Create a task for Rossi tomorrow';
        $command = $this->provider([
            'intent' => 'create_task',
            'confidence' => 0.82,
            'entities' => [],
            'notes' => [],
        ])->interpret($text, $this->context());

        $this->assertSame($text, $command->sourceText);
    }

    public function test_provider_preserves_locale_from_context(): void
    {
        $command = $this->provider([
            'intent' => 'create_task',
            'confidence' => 0.82,
            'entities' => [],
            'notes' => [],
        ])->interpret('Crea un task', $this->context('it'));

        $this->assertSame('it', $command->locale);
    }

    public function test_unknown_intent_is_accepted_when_validator_accepts_it(): void
    {
        $command = $this->provider([
            'intent' => 'unknown',
            'confidence' => 0.1,
            'entities' => [],
            'notes' => [],
        ])->interpret('Show me the dashboard', $this->context());

        $this->assertSame('unknown', $command->intent);
        $this->assertSame(0.1, $command->confidence);
    }

    // ── Fail closed ──────────────────────────────────────────────────────────

    public function test_invalid_structured_output_fails_closed(): void
    {
        // confidence out of range — contract violation
        $this->expectException(InvalidLlmStructuredOutputException::class);

        $this->provider([
            'intent' => 'create_task',
            'confidence' => 1.7,
            'entities' => [],
            'notes' => [],
        ])->interpret('Create a task', $this->context());
    }

    public function test_unsupported_intent_fails_through_validator(): void
    {
        $this->expectException(InvalidLlmStructuredOutputException::class);

        $this->provider([
            'intent' => 'delete_everything',
            'confidence' => 0.9,
            'entities' => [],
            'notes' => [],
        ])->interpret('Delete everything', $this->context());
    }

    public function test_malformed_provider_response_fails_closed(): void
    {
        // No structured JSON in the transport response.
        $this->expectException(InvalidLlmStructuredOutputException::class);

        $this->provider(null)->interpret('Create a task', $this->context());
    }

    public function test_forbidden_entity_key_fails_closed(): void
    {
        $this->expectException(InvalidLlmStructuredOutputException::class);

        $this->provider([
            'intent' => 'create_task',
            'confidence' => 0.8,
            'entities' => ['lead_id' => 42],
            'notes' => [],
        ])->interpret('Create a task for lead 42', $this->context());
    }

    // ── Adapter boundary: NormalizedCommandValidator still runs ───────────────

    public function test_provider_output_flows_through_normalized_command_validator(): void
    {
        $adapter = new InterpretationProviderAdapter(
            $this->provider([
                'intent' => 'create_task',
                'confidence' => 0.82,
                'entities' => ['lead' => 'Rossi'],
                'notes' => [],
            ]),
            $this->app->make(NormalizedCommandValidator::class),
        );

        $command = $adapter->interpret('Create a task for Rossi');

        $this->assertInstanceOf(NormalizedCommand::class, $command);
        $this->assertSame('create_task', $command->intent);
    }

    public function test_adapter_rejects_command_that_normalized_validator_refuses(): void
    {
        // A FakeLlm-style provider returning a command with an empty intent
        // proves the adapter's NormalizedCommandValidator is not bypassed,
        // independent of the LLM structured-output validator.
        $provider = new class implements InterpretationProviderInterface
        {
            public function interpret(string $text, InterpretationContext $context): NormalizedCommand
            {
                return new NormalizedCommand(
                    intent: '',
                    confidence: 0.9,
                    sourceText: $text,
                    locale: $context->locale,
                );
            }
        };

        $adapter = new InterpretationProviderAdapter(
            $provider,
            $this->app->make(NormalizedCommandValidator::class),
        );

        $this->expectException(InvalidNormalizedCommandException::class);
        $adapter->interpret('whatever');
    }
}
