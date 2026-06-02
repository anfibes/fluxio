<?php

namespace Fluxio\Actions\Diagnostics\Observation\DTO;

/**
 * Diagnostics-only snapshot of how a real provider responded to one observation case.
 *
 * It captures every stage of the interpretation-side pipeline — raw model text, parsed
 * structured JSON, the structured-output validation outcome, the NormalizedCommand that
 * would be produced, and the adapter-equivalent sandbox / runtime-validation outcome —
 * plus a partial match against the case's expectations. Nothing here is authoritative:
 * no proposal is built, nothing is executed, and no provider output reaches the runtime.
 */
final class LlmObservationResult
{
    /**
     * @param  array<string, mixed>  $expectedEntities
     * @param  list<string>  $notes
     * @param  array<string, mixed>|null  $parsedJson
     * @param  list<string>  $structuredOutputErrors
     * @param  array{intent: string, confidence: float, entities: array<string, mixed>, warnings: list<string>}|null  $normalizedCommand
     * @param  list<string>  $sandboxViolations
     * @param  list<string>  $normalizedValidationErrors
     */
    public function __construct(
        public readonly string $id,
        public readonly string $text,
        public readonly string $locale,
        public readonly string $expectedIntent,
        public readonly array $expectedEntities,
        public readonly array $notes,
        public readonly bool $providerSucceeded,
        public readonly ?string $failureClass,
        public readonly ?string $failureMessage,
        public readonly ?string $rawResponse,
        public readonly ?array $parsedJson,
        public readonly ?bool $structuredOutputValid,
        public readonly array $structuredOutputErrors,
        public readonly ?array $normalizedCommand,
        public readonly array $sandboxViolations,
        public readonly array $normalizedValidationErrors,
        public readonly ?bool $intentMatch,
        public readonly ?bool $entityMatch,
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
            'expected' => [
                'intent' => $this->expectedIntent,
                'entities' => $this->expectedEntities,
            ],
            'notes' => $this->notes,
            'provider' => [
                'succeeded' => $this->providerSucceeded,
                'failure_class' => $this->failureClass,
                'failure_message' => $this->failureMessage,
            ],
            'raw_response' => $this->rawResponse,
            'parsed_json' => $this->parsedJson,
            'structured_output' => [
                'valid' => $this->structuredOutputValid,
                'errors' => $this->structuredOutputErrors,
            ],
            'normalized_command' => $this->normalizedCommand,
            'adapter_outcome' => [
                'sandbox_violations' => $this->sandboxViolations,
                'normalized_validation_errors' => $this->normalizedValidationErrors,
            ],
            'match' => [
                'intent' => $this->intentMatch,
                'entities' => $this->entityMatch,
            ],
        ];
    }
}
