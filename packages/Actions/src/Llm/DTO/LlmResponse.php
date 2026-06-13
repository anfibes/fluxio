<?php

namespace Fluxio\Actions\Llm\DTO;

/**
 * Generic LLM response DTO.
 *
 * Transport-level only. Carries the raw generated text, an optional
 * decoded JSON payload (when structured output was requested), and opaque
 * usage/provider metadata for tracing. This DTO is intentionally unaware
 * of Fluxio domain concepts: it does not know about intents, entities,
 * NormalizedCommand, or proposal state. Business validation (allowed
 * intents, entity keys, confidence range) happens in the future
 * LLM-backed InterpretationProvider, never here.
 */
final readonly class LlmResponse
{
    /**
     * @param  array<string, mixed>|null  $parsedJson  Decoded JSON when structured output
     *                                                 was requested and parsing succeeded.
     * @param  array<string, mixed>  $usage  Token / timing usage metadata.
     * @param  array<string, mixed>  $providerMetadata  Opaque provider-specific fields
     *                                                  (model name, eval counts, …).
     * @param  array<string, mixed>|null  $rawBody  Full decoded provider response body for
     *                                              diagnostics tracing (e.g. a reasoning
     *                                              model's `thinking` field that the whitelisted
     *                                              metadata would otherwise drop). Diagnostics
     *                                              only; never consumed by the runtime.
     */
    public function __construct(
        public string $rawText,
        public ?array $parsedJson = null,
        public array $usage = [],
        public array $providerMetadata = [],
        public ?array $rawBody = null,
    ) {}
}
