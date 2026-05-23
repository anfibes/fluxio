<?php

namespace Fluxio\Actions\Llm\Contracts;

use Fluxio\Actions\Llm\DTO\LlmRequest;
use Fluxio\Actions\Llm\DTO\LlmResponse;
use Fluxio\Actions\Llm\Exceptions\InvalidLlmResponseException;
use Fluxio\Actions\Llm\Exceptions\LlmTransportException;

/**
 * Generic LLM transport boundary.
 *
 * Implementations adapt a concrete provider (Ollama, OpenAI-compatible
 * endpoint, LM Studio, llama.cpp server, …) to a single uniform call.
 * The interface is intentionally minimal and Fluxio-agnostic.
 *
 * Implementations must throw:
 *  - LlmTransportException for transport-level failures (timeout, connection
 *    error, non-2xx HTTP response, unavailable provider).
 *  - InvalidLlmResponseException for malformed provider responses, missing
 *    response text, or invalid JSON when structured output was requested.
 *
 * Implementations must NOT validate Fluxio business semantics (intents,
 * entity keys, confidence). That belongs to the interpretation provider
 * layer that consumes this client.
 */
interface LlmClientInterface
{
    /**
     * Perform a single generation call. When $request->jsonSchema is non-null
     * the client should request structured JSON output from the backend and
     * populate LlmResponse::$parsedJson with the decoded array.
     *
     * @throws LlmTransportException
     * @throws InvalidLlmResponseException
     */
    public function generateStructured(LlmRequest $request): LlmResponse;
}
