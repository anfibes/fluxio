<?php

namespace Fluxio\Actions\Llm\DTO;

/**
 * Phase 9F.1C — the provider-internal structured-output contract, made explicit.
 *
 * This is the typed shape of the JSON an LLM interpretation provider must return:
 *
 *   {"intent": "...", "confidence": 0.0, "entities": {}, "notes": []}
 *
 * It exists only to name that four-field contract in one place and project a
 * validated payload into a typed value, so OllamaInterpretationProvider maps to
 * NormalizedCommand from named fields instead of re-deriving the shape with raw
 * array access and defensive defaults.
 *
 * Boundaries:
 *  - PROVIDER-INTERNAL ONLY. It must never cross into ActionInterpreterService or
 *    any runtime authority; providers still return NormalizedCommand.
 *  - It does NOT validate and does NOT replace LlmStructuredOutputValidator. It
 *    assumes a payload the validator has already accepted — presence and shape of
 *    every field are the validator's guarantee, not this DTO's.
 *  - `notes` are non-authoritative interpretation warnings only; they carry no
 *    lifecycle, identity, or execution meaning.
 */
final class LlmStructuredOutput
{
    /**
     * @param  array<string, mixed>  $entities  Raw references / parsed values, validator-accepted
     * @param  list<string>  $notes  Non-authoritative interpretation warnings
     */
    private function __construct(
        public readonly string $intent,
        public readonly float $confidence,
        public readonly array $entities,
        public readonly array $notes,
    ) {}

    /**
     * Project a payload ALREADY validated by LlmStructuredOutputValidator into the
     * typed contract. `intent`, `confidence` and `entities` are guaranteed present by
     * the validator; `notes` is optional (defaults to an empty list).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromValidatedPayload(array $payload): self
    {
        return new self(
            intent: (string) $payload['intent'],
            confidence: (float) $payload['confidence'],
            entities: $payload['entities'],
            notes: array_values($payload['notes'] ?? []),
        );
    }
}
