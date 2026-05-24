<?php

namespace Fluxio\Actions\Diagnostics\Evaluation\DTO;

use Fluxio\Actions\Diagnostics\DTO\InterpretationComparisonResult;

/**
 * Per-case evaluation outcome: the diagnostics comparison for one corpus case,
 * plus whether each provider matched the case's expected intent and expected
 * (partial, exact-match) entities. Diagnostics-only — nothing here is
 * authoritative and no proposal is built.
 */
final class InterpretationEvaluationResult
{
    /**
     * @param  array<string, mixed>  $expectedEntities
     */
    public function __construct(
        public readonly string $id,
        public readonly string $text,
        public readonly string $locale,
        public readonly string $expectedIntent,
        public readonly array $expectedEntities,
        public readonly InterpretationComparisonResult $comparison,
        public readonly bool $deterministicIntentMatch,
        public readonly bool $ollamaIntentMatch,
        public readonly bool $deterministicEntityMatch,
        public readonly bool $ollamaEntityMatch,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'text' => $this->text,
            'locale' => $this->locale,
            'expected_intent' => $this->expectedIntent,
            'expected_entities' => $this->expectedEntities,
            'deterministic_intent_match' => $this->deterministicIntentMatch,
            'ollama_intent_match' => $this->ollamaIntentMatch,
            'deterministic_entity_match' => $this->deterministicEntityMatch,
            'ollama_entity_match' => $this->ollamaEntityMatch,
            'comparison' => $this->comparison->toArray(),
        ];
    }
}
