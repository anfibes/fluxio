<?php

namespace Fluxio\Actions\Registry;

use Fluxio\Actions\DTO\IntentNarrationDefinition;

/**
 * In-memory, per-intent registry of narration metadata.
 *
 * Parallel to IntentCapabilityRegistry: a separate presentation registry that sits
 * beside IntentRegistry/IntentDefinition without coupling runtime authority to
 * presentation. It owns only narration metadata (which intents are narratable and
 * the translation key of their canonical phrase). It is never persisted and never
 * an input to readiness, validation, resolution, confirmation, or execution.
 */
class IntentNarrationRegistry
{
    /** @var array<string, IntentNarrationDefinition> */
    private array $definitions = [];

    public function register(IntentNarrationDefinition $definition): void
    {
        $this->definitions[$definition->intent] = $definition;
    }

    public function find(string $intent): ?IntentNarrationDefinition
    {
        return $this->definitions[$intent] ?? null;
    }

    /** @return array<string, IntentNarrationDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }
}
