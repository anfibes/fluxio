<?php

namespace Tests\Unit\Actions\Llm;

use Fluxio\Actions\Llm\Clients\OllamaLlmClient;
use Fluxio\Actions\Llm\DTO\LlmRequest;
use Fluxio\Actions\Llm\Exceptions\InvalidLlmResponseException;
use Fluxio\Actions\Llm\Exceptions\LlmTransportException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for the Phase 2 LLM transport client.
 *
 * No real HTTP calls are made: every test uses Laravel's Http::fake().
 * The client is exercised in isolation — proposal lifecycle, NormalizedCommand
 * validation and the interpretation provider stack are not involved.
 */
class OllamaLlmClientTest extends TestCase
{
    private function client(string $model = 'qwen3:0.6b', int $timeout = 10): OllamaLlmClient
    {
        return new OllamaLlmClient(
            http: $this->app->make(HttpFactory::class),
            baseUrl: 'http://127.0.0.1:11434',
            defaultModel: $model,
            timeout: $timeout,
        );
    }

    public function test_serializes_request_payload_and_targets_generate_endpoint(): void
    {
        Http::fake([
            '127.0.0.1:11434/api/generate' => Http::response([
                'response' => 'hello',
                'model' => 'qwen3:0.6b',
                'done' => true,
            ]),
        ]);

        $client = $this->client();
        $client->generateStructured(new LlmRequest(
            prompt: 'say hi',
            systemPrompt: 'you are concise',
            model: 'qwen3:0.6b',
            temperature: 0.2,
            maxTokens: 16,
        ));

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'http://127.0.0.1:11434/api/generate'
                && $request->method() === 'POST'
                && $body['model'] === 'qwen3:0.6b'
                && $body['prompt'] === 'say hi'
                && $body['system'] === 'you are concise'
                && $body['stream'] === false
                && ! array_key_exists('format', $body) // no jsonSchema → no format=json
                && $body['options']['temperature'] === 0.2
                && $body['options']['num_predict'] === 16;
        });
    }

    public function test_requests_format_json_when_json_schema_is_provided(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => '{"intent":"create_task","confidence":0.8,"entities":{}}',
                'model' => 'qwen3:0.6b',
                'done' => true,
            ]),
        ]);

        $client = $this->client();
        $response = $client->generateStructured(new LlmRequest(
            prompt: 'irrelevant',
            jsonSchema: ['type' => 'object'],
        ));

        Http::assertSent(fn ($request) => ($request->data()['format'] ?? null) === 'json');
        $this->assertSame(['intent' => 'create_task', 'confidence' => 0.8, 'entities' => []], $response->parsedJson);
        $this->assertSame('{"intent":"create_task","confidence":0.8,"entities":{}}', $response->rawText);
    }

    public function test_returns_raw_text_and_metadata_for_unstructured_response(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => 'plain answer',
                'model' => 'qwen3:0.6b',
                'done' => true,
                'prompt_eval_count' => 4,
                'eval_count' => 12,
                'total_duration' => 123456,
            ]),
        ]);

        $response = $this->client()->generateStructured(new LlmRequest(prompt: 'hi'));

        $this->assertSame('plain answer', $response->rawText);
        $this->assertNull($response->parsedJson);
        $this->assertSame(4, $response->usage['prompt_eval_count']);
        $this->assertSame(12, $response->usage['eval_count']);
        $this->assertSame(123456, $response->usage['total_duration']);
        $this->assertSame('qwen3:0.6b', $response->providerMetadata['model']);
        $this->assertTrue($response->providerMetadata['done']);
    }

    public function test_throws_invalid_response_when_structured_output_is_not_json(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => 'this is not json',
                'model' => 'qwen3:0.6b',
                'done' => true,
            ]),
        ]);

        $this->expectException(InvalidLlmResponseException::class);

        $this->client()->generateStructured(new LlmRequest(
            prompt: 'irrelevant',
            jsonSchema: ['type' => 'object'],
        ));
    }

    public function test_throws_invalid_response_when_response_field_is_missing(): void
    {
        Http::fake([
            '*' => Http::response([
                'model' => 'qwen3:0.6b',
                'done' => true,
            ]),
        ]);

        $this->expectException(InvalidLlmResponseException::class);

        $this->client()->generateStructured(new LlmRequest(prompt: 'hi'));
    }

    public function test_throws_invalid_response_when_body_is_not_json_object(): void
    {
        Http::fake([
            '*' => Http::response('not an object', 200, ['Content-Type' => 'application/json']),
        ]);

        $this->expectException(InvalidLlmResponseException::class);

        $this->client()->generateStructured(new LlmRequest(prompt: 'hi'));
    }

    public function test_throws_transport_exception_on_non_successful_http_status(): void
    {
        Http::fake([
            '*' => Http::response(['error' => 'model not loaded'], 503),
        ]);

        $this->expectException(LlmTransportException::class);

        $this->client()->generateStructured(new LlmRequest(prompt: 'hi'));
    }

    public function test_throws_transport_exception_on_connection_failure(): void
    {
        Http::fake(function () {
            throw new ConnectionException('connection refused');
        });

        $this->expectException(LlmTransportException::class);

        $this->client()->generateStructured(new LlmRequest(prompt: 'hi'));
    }

    public function test_uses_default_model_when_request_model_is_null(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => 'ok',
                'model' => 'llama3.2:3b',
                'done' => true,
            ]),
        ]);

        $this->client('llama3.2:3b')->generateStructured(new LlmRequest(prompt: 'hi'));

        Http::assertSent(fn ($request) => $request->data()['model'] === 'llama3.2:3b');
    }

    // ── A7.1/E0.5: full raw body capture ──────────────────────────────────────

    public function test_captures_the_full_raw_body_for_diagnostics(): void
    {
        Http::fake([
            '*' => Http::response([
                'response' => 'plain',
                'model' => 'qwen3:1.7b',
                'done' => true,
                // Fields outside the whitelist — must still be visible for diagnostics.
                'thinking' => 'let me reason about this...',
                'context' => [1, 2, 3],
            ]),
        ]);

        $response = $this->client()->generateStructured(new LlmRequest(prompt: 'hi'));

        $this->assertIsArray($response->rawBody);
        $this->assertSame('let me reason about this...', $response->rawBody['thinking']);
        $this->assertSame([1, 2, 3], $response->rawBody['context']);
        $this->assertSame('plain', $response->rawBody['response']);
    }

    // ── A7.1/E1: thinking toggle ──────────────────────────────────────────────

    public function test_omits_think_from_the_payload_by_default(): void
    {
        Http::fake(['*' => Http::response(['response' => 'ok', 'model' => 'm', 'done' => true])]);

        $this->client()->generateStructured(new LlmRequest(prompt: 'hi'));

        Http::assertSent(fn ($request) => ! array_key_exists('think', $request->data()));
    }

    public function test_sends_think_false_only_when_explicitly_requested(): void
    {
        Http::fake(['*' => Http::response(['response' => '{}', 'model' => 'm', 'done' => true])]);

        $this->client()->generateStructured(new LlmRequest(
            prompt: 'hi',
            jsonSchema: ['type' => 'object'],
            think: false,
        ));

        Http::assertSent(fn ($request) => ($request->data()['think'] ?? '__absent__') === false);
    }
}
