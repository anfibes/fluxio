<?php

namespace Fluxio\Actions\DTO;

class IntentDefinition
{
    /**
     * @param list<string> $requiredEntities Entity keys that must be present for readiness
     * @param list<string> $optionalEntities Entity keys that are recognised but not required
     */
    public function __construct(
        public readonly string $intent,
        public readonly string $label,
        public readonly string $module,
        public readonly string $operation,
        public readonly array  $requiredEntities,
        public readonly array  $optionalEntities,
        public readonly string $executorClass,
        public readonly float  $confidence,
        public readonly bool   $needsConfirmation = true,
    ) {}
}
