<?php

namespace Fluxio\Actions\Llm\DTO;

/**
 * Generic LLM request DTO.
 *
 * Transport-level only. Carries the prompt, model and decoding hints that
 * the LlmClientInterface implementation needs to make a single generation
 * call. It must not carry Fluxio business semantics (intents, entities,
 * proposals, etc.) — those belong to the future LLM-backed interpretation
 * provider that will build LlmRequest objects from higher-level context.
 */
final readonly class LlmRequest
{
    /**
     * @param  array<string, mixed>|null  $jsonSchema  Optional JSON schema hint. When provided
     *                                                 the client should request structured JSON
     *                                                 output from the backend (e.g. format=json
     *                                                 on Ollama). The schema itself is not
     *                                                 enforced at the transport layer.
     * @param  array<string, mixed>  $metadata  Provider-agnostic metadata; opaque to the
     *                                          client. Useful for tracing / logging.
     * @param  bool|null  $think  Optional reasoning toggle for "thinking" models
     *                            (e.g. Ollama's `think` parameter). Null (default)
     *                            leaves it unset, so the backend's own default
     *                            applies — byte-identical to the prior wire payload.
     *                            Provider-agnostic; the client maps it to the
     *                            backend's field. Diagnostics use it; the runtime
     *                            never sets it.
     */
    public function __construct(
        public string $prompt,
        public ?string $systemPrompt = null,
        public ?string $model = null,
        public ?float $temperature = null,
        public ?int $maxTokens = null,
        public ?array $jsonSchema = null,
        public array $metadata = [],
        public ?bool $think = null,
    ) {}
}
