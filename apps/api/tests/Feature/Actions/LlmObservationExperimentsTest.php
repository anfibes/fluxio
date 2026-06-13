<?php

namespace Tests\Feature\Actions;

use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationCorpusCase;
use Fluxio\Actions\Diagnostics\Observation\DTO\ObservationOptions;
use Fluxio\Actions\Diagnostics\Observation\LlmObservationService;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;
use Fluxio\Actions\Interpretation\Providers\OllamaInterpretationProvider;
use Fluxio\Actions\Llm\Contracts\LlmClientInterface;
use Fluxio\Actions\Llm\DTO\LlmRequest;
use Fluxio\Actions\Llm\DTO\LlmResponse;
use Fluxio\Actions\Llm\Prompting\InterpretationPromptBuilder;
use Fluxio\Actions\Llm\Validation\LlmStructuredOutputValidator;
use Tests\TestCase;

/**
 * A7.1 diagnostics experiments at the LlmObservationService boundary. A request-recording fake
 * client mirrors the real client's contract (parsedJson only in forced-JSON mode) so each knob —
 * thinking off (E1), format off + extract (E2), generation budget (E3), raw capture (E0.5) — is
 * exercised deterministically with no real model. The SAME validator is used throughout.
 */
class LlmObservationExperimentsTest extends TestCase
{
    private function recordingClient(string $rawText, ?array $parsedJson, ?array $rawBody = null): object
    {
        $client = new class($rawText, $parsedJson, $rawBody) implements LlmClientInterface
        {
            public ?LlmRequest $lastRequest = null;

            public function __construct(
                private readonly string $rawText,
                private readonly ?array $parsedJson,
                private readonly ?array $rawBody,
            ) {}

            public function generateStructured(LlmRequest $request): LlmResponse
            {
                $this->lastRequest = $request;

                // Mirror OllamaLlmClient: parsedJson is populated only when JSON is forced.
                $parsed = $request->jsonSchema !== null ? $this->parsedJson : null;

                return new LlmResponse(rawText: $this->rawText, parsedJson: $parsed, rawBody: $this->rawBody);
            }
        };

        $this->app->instance(LlmClientInterface::class, $client);

        return $client;
    }

    private function case(): InterpretationCorpusCase
    {
        return new InterpretationCorpusCase(
            id: 'probe',
            text: 'Crea un task per Bianchi',
            locale: 'it',
            expectedIntent: 'create_task',
            expectedEntities: ['lead' => 'Bianchi'],
        );
    }

    private function validCreateTask(): array
    {
        return ['intent' => 'create_task', 'confidence' => 0.9, 'entities' => ['lead' => 'Bianchi'], 'notes' => []];
    }

    private function service(): LlmObservationService
    {
        return $this->app->make(LlmObservationService::class);
    }

    // ── Default (must stay byte-identical to the prior behavior) ──────────────

    public function test_default_options_force_json_and_set_no_thinking_override(): void
    {
        $client = $this->recordingClient((string) json_encode($this->validCreateTask()), $this->validCreateTask());

        $result = $this->service()->observe($this->case());

        $this->assertNull($client->lastRequest->think, 'Default run must not send a thinking override.');
        $this->assertSame(['type' => 'object'], $client->lastRequest->jsonSchema, 'Default run must force JSON.');
        $this->assertNull($client->lastRequest->maxTokens);
        $this->assertTrue($result->structuredOutputValid);
        $this->assertSame('create_task', $result->normalizedCommand['intent']);
    }

    // ── E1: thinking off ──────────────────────────────────────────────────────

    public function test_thinking_off_threads_think_false_but_keeps_prompt_format_validator(): void
    {
        $client = $this->recordingClient((string) json_encode($this->validCreateTask()), $this->validCreateTask());

        $this->service()->observe($this->case(), [], null, new ObservationOptions(think: false));

        $this->assertFalse($client->lastRequest->think);
        // Format/JSON contract unchanged — only thinking is toggled.
        $this->assertSame(['type' => 'object'], $client->lastRequest->jsonSchema);
    }

    // ── E2: format off + extract, judged by the SAME validator ────────────────

    public function test_free_text_mode_extracts_json_and_validates_it(): void
    {
        $rawText = "<think>The user wants a task for Bianchi.</think>\n".(string) json_encode($this->validCreateTask());
        // parsedJson is null in free-text mode (no format=json); extraction must recover it.
        $client = $this->recordingClient($rawText, null);

        $result = $this->service()->observe($this->case(), [], null, new ObservationOptions(forceJson: false));

        $this->assertNull($client->lastRequest->jsonSchema, 'Free-text mode must not force JSON.');
        $this->assertTrue($result->structuredOutputValid, 'Extracted object must pass the same validator.');
        $this->assertSame('create_task', $result->normalizedCommand['intent']);
    }

    public function test_free_text_mode_reports_when_no_json_object_is_present(): void
    {
        $client = $this->recordingClient('Mi dispiace, non posso aiutarti.', null);

        $result = $this->service()->observe($this->case(), [], null, new ObservationOptions(forceJson: false));

        $this->assertNull($client->lastRequest->jsonSchema);
        $this->assertFalse($result->structuredOutputValid);
        $this->assertNull($result->normalizedCommand);
        $this->assertStringContainsString('no extractable JSON object', implode(' ', $result->structuredOutputErrors));
    }

    public function test_free_text_mode_does_not_relax_validation(): void
    {
        // Extracted object is well-formed JSON but violates the contract (unknown intent).
        $rawText = '<think>…</think>{"intent":"hallucinated","confidence":0.9,"entities":{},"notes":[]}';
        $this->recordingClient($rawText, null);

        $result = $this->service()->observe($this->case(), [], null, new ObservationOptions(forceJson: false));

        $this->assertFalse($result->structuredOutputValid);
        $this->assertNull($result->normalizedCommand);
        $this->assertNotEmpty($result->structuredOutputErrors);
    }

    // ── E3: generation budget ─────────────────────────────────────────────────

    public function test_num_predict_is_threaded_as_max_tokens(): void
    {
        $client = $this->recordingClient((string) json_encode($this->validCreateTask()), $this->validCreateTask());

        $this->service()->observe($this->case(), [], null, new ObservationOptions(maxTokens: 512));

        $this->assertSame(512, $client->lastRequest->maxTokens);
    }

    // ── E0.5: raw body capture ────────────────────────────────────────────────

    public function test_raw_body_is_captured_in_the_result(): void
    {
        $rawBody = ['response' => '{}', 'thinking' => 'long reasoning trace', 'done' => true];
        $this->recordingClient('{}', null, $rawBody);

        $result = $this->service()->observe($this->case());

        $this->assertSame($rawBody, $result->rawBody);
        $this->assertSame($rawBody, $result->toArray()['raw_body']);
    }

    // ── Runtime path remains unchanged (provider-blind, no thinking override) ──

    public function test_runtime_provider_never_sends_a_thinking_override_or_drops_forced_json(): void
    {
        $client = $this->recordingClient((string) json_encode($this->validCreateTask()), $this->validCreateTask());

        $provider = new OllamaInterpretationProvider(
            client: $client,
            validator: $this->app->make(LlmStructuredOutputValidator::class),
            promptBuilder: $this->app->make(InterpretationPromptBuilder::class),
        );

        $provider->interpret('Create a task for Bianchi', new InterpretationContext(locale: 'en'));

        $this->assertNull($client->lastRequest->think, 'Runtime must never set a thinking override.');
        $this->assertSame(['type' => 'object'], $client->lastRequest->jsonSchema, 'Runtime must keep forced JSON.');
    }
}
