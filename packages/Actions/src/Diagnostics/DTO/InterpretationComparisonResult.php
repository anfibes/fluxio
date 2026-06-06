<?php

namespace Fluxio\Actions\Diagnostics\DTO;

/**
 * Diagnostics-only structured comparison of two interpretation providers for a
 * single input. This is not runtime hybrid mode: nothing here becomes
 * authoritative, no proposal is built, and the configured runtime provider is
 * untouched. It exists purely to inspect deterministic vs. Ollama divergence
 * during development.
 */
final class InterpretationComparisonResult
{
    /**
     * @param  array{only_in_deterministic: array<string, mixed>, only_in_ollama: array<string, mixed>, different_values: array<string, array{deterministic: mixed, ollama: mixed}>, same_values: array<string, mixed>}  $entityDiff
     * @param  array{only_in_deterministic: list<string>, only_in_ollama: list<string>, common: list<string>}  $warningDiff
     * @param  list<string>  $notes
     */
    public function __construct(
        public readonly string $text,
        public readonly InterpretationProviderResult $deterministic,
        public readonly InterpretationProviderResult $ollama,
        public readonly bool $bothSucceeded,
        public readonly bool $anyFailed,
        public readonly ?bool $sameIntent,
        public readonly ?float $confidenceDelta,
        public readonly array $entityDiff,
        public readonly array $warningDiff,
        public readonly array $notes,
        // Diagnostics timing only: total wall-clock ms for this case (deterministic +
        // provider interpret() calls). Observability for local-model comparison.
        public readonly float $caseDurationMs = 0.0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'both_succeeded' => $this->bothSucceeded,
            'any_failed' => $this->anyFailed,
            'same_intent' => $this->sameIntent,
            'confidence_delta' => $this->confidenceDelta,
            'deterministic' => $this->deterministic->toArray(),
            'ollama' => $this->ollama->toArray(),
            'entity_diff' => $this->entityDiff,
            'warning_diff' => $this->warningDiff,
            'notes' => $this->notes,
            'duration_ms' => $this->caseDurationMs,
        ];
    }
}
