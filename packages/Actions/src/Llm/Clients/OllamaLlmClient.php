<?php

namespace Fluxio\Actions\Llm\Clients;

use Fluxio\Actions\Llm\Contracts\LlmClientInterface;
use Fluxio\Actions\Llm\DTO\LlmRequest;
use Fluxio\Actions\Llm\DTO\LlmResponse;
use Fluxio\Actions\Llm\Exceptions\InvalidLlmResponseException;
use Fluxio\Actions\Llm\Exceptions\LlmTransportException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Throwable;

/**
 * Ollama-backed LLM client.
 *
 * Talks to the Ollama HTTP API (POST /api/generate). Streaming is
 * disabled; when LlmRequest::$jsonSchema is non-null the client requests
 * structured JSON output via format=json and decodes the response text
 * into LlmResponse::$parsedJson.
 *
 * Ollama is the first adapter — the architecture itself is not
 * Ollama-specific. The model is configurable; no Fluxio intent names or
 * business prompts are hardcoded here.
 */
class OllamaLlmClient implements LlmClientInterface
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string      $baseUrl,
        private readonly string      $defaultModel,
        private readonly int         $timeout = 10,
    ) {}

    public function generateStructured(LlmRequest $request): LlmResponse
    {
        $payload = [
            'model'  => $request->model ?? $this->defaultModel,
            'prompt' => $request->prompt,
            'stream' => false,
        ];

        if ($request->systemPrompt !== null) {
            $payload['system'] = $request->systemPrompt;
        }

        if ($request->jsonSchema !== null) {
            $payload['format'] = 'json';
        }

        $options = [];
        if ($request->temperature !== null) {
            $options['temperature'] = $request->temperature;
        }
        if ($request->maxTokens !== null) {
            $options['num_predict'] = $request->maxTokens;
        }
        if (! empty($options)) {
            $payload['options'] = $options;
        }

        try {
            $response = $this->http
                ->timeout($this->timeout)
                ->acceptJson()
                ->asJson()
                ->post($this->endpoint(), $payload);
        } catch (ConnectionException $e) {
            throw new LlmTransportException(
                "Ollama transport failure: {$e->getMessage()}",
                previous: $e,
            );
        } catch (Throwable $e) {
            throw new LlmTransportException(
                "Ollama transport failure: {$e->getMessage()}",
                previous: $e,
            );
        }

        if (! $response->successful()) {
            throw new LlmTransportException(
                "Ollama returned HTTP {$response->status()}.",
            );
        }

        try {
            $body = $response->json();
        } catch (RequestException $e) {
            throw new InvalidLlmResponseException(
                'Ollama response body is not valid JSON.',
                previous: $e,
            );
        }

        if (! is_array($body)) {
            throw new InvalidLlmResponseException('Ollama response body is not a JSON object.');
        }

        $rawText = $body['response'] ?? null;
        if (! is_string($rawText)) {
            throw new InvalidLlmResponseException('Ollama response is missing the "response" string field.');
        }

        $parsedJson = null;
        if ($request->jsonSchema !== null) {
            $decoded = json_decode($rawText, true);
            if (! is_array($decoded)) {
                throw new InvalidLlmResponseException(
                    'Ollama returned non-JSON content when structured output was requested.',
                );
            }
            $parsedJson = $decoded;
        }

        return new LlmResponse(
            rawText:          $rawText,
            parsedJson:       $parsedJson,
            usage:            $this->extractUsage($body),
            providerMetadata: $this->extractProviderMetadata($body),
        );
    }

    private function endpoint(): string
    {
        return rtrim($this->baseUrl, '/') . '/api/generate';
    }

    /**
     * @param  array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function extractUsage(array $body): array
    {
        $usage = [];
        foreach (['prompt_eval_count', 'eval_count', 'total_duration', 'load_duration', 'prompt_eval_duration', 'eval_duration'] as $key) {
            if (array_key_exists($key, $body)) {
                $usage[$key] = $body[$key];
            }
        }

        return $usage;
    }

    /**
     * @param  array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function extractProviderMetadata(array $body): array
    {
        $meta = [];
        foreach (['model', 'created_at', 'done', 'done_reason'] as $key) {
            if (array_key_exists($key, $body)) {
                $meta[$key] = $body[$key];
            }
        }

        return $meta;
    }
}
