<?php

namespace Fluxio\Actions\Diagnostics\DTO;

use Fluxio\Actions\DTO\NormalizedCommand;
use Throwable;

/**
 * Diagnostics-only snapshot of a single interpretation provider's outcome.
 *
 * A failed provider is captured as data (success=false plus error class/message)
 * rather than propagated, so a comparison can report it without crashing. This
 * is development diagnostics only — it never feeds the proposal pipeline and
 * does not weaken the runtime fail-closed behaviour of any provider.
 */
final class InterpretationProviderResult
{
    /**
     * @param  array<string, mixed>  $entities
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly string $providerKey,
        public readonly bool $success,
        public readonly ?string $intent = null,
        public readonly ?float $confidence = null,
        public readonly array $entities = [],
        public readonly array $warnings = [],
        public readonly ?string $errorClass = null,
        public readonly ?string $errorMessage = null,
        // Diagnostics timing only: wall-clock ms spent in this provider's interpret()
        // call (success or failure). Observability for local-model comparison; never
        // consulted by runtime interpretation.
        public readonly ?float $durationMs = null,
    ) {}

    public static function fromCommand(string $providerKey, NormalizedCommand $command, ?float $durationMs = null): self
    {
        return new self(
            providerKey: $providerKey,
            success: true,
            intent: $command->intent,
            confidence: $command->confidence,
            entities: $command->entities,
            warnings: $command->warnings,
            durationMs: $durationMs,
        );
    }

    public static function fromFailure(string $providerKey, Throwable $e, ?float $durationMs = null): self
    {
        return new self(
            providerKey: $providerKey,
            success: false,
            errorClass: $e::class,
            errorMessage: $e->getMessage(),
            durationMs: $durationMs,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'provider' => $this->providerKey,
            'success' => $this->success,
            'intent' => $this->intent,
            'confidence' => $this->confidence,
            'entities' => $this->entities,
            'warnings' => $this->warnings,
            'error_class' => $this->errorClass,
            'error_message' => $this->errorMessage,
            'duration_ms' => $this->durationMs,
        ];
    }
}
