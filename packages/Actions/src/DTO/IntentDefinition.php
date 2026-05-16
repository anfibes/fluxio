<?php

namespace Fluxio\Actions\DTO;

use Fluxio\Actions\Enums\ConfirmationPolicy;
use Fluxio\Actions\Enums\IntentComplexity;

class IntentDefinition
{
    /** @param list<EntityRequirement> $requirements */
    public function __construct(
        public readonly string             $intent,
        public readonly string             $label,
        public readonly string             $module,
        public readonly string             $operation,
        public readonly array              $requirements,
        public readonly string             $executorClass,
        public readonly float              $confidence,
        public readonly IntentComplexity   $complexity         = IntentComplexity::Simple,
        public readonly ConfirmationPolicy $confirmationPolicy = ConfirmationPolicy::Required,
        public readonly bool               $needsConfirmation  = true,
    ) {}

    /** @return list<EntityRequirement> */
    public function requiredRequirements(): array
    {
        return array_values(array_filter($this->requirements, fn (EntityRequirement $r) => $r->required));
    }

    /** @return list<EntityRequirement> */
    public function optionalRequirements(): array
    {
        return array_values(array_filter($this->requirements, fn (EntityRequirement $r) => ! $r->required));
    }

    /** @return list<string> */
    public function requirementKeys(): array
    {
        return array_map(fn (EntityRequirement $r) => $r->key, $this->requirements);
    }

    /** @return list<string> */
    public function requiredKeys(): array
    {
        return array_map(fn (EntityRequirement $r) => $r->key, $this->requiredRequirements());
    }

    /** @return list<string> */
    public function optionalKeys(): array
    {
        return array_map(fn (EntityRequirement $r) => $r->key, $this->optionalRequirements());
    }

    public function findRequirement(string $key): ?EntityRequirement
    {
        foreach ($this->requirements as $req) {
            if ($req->key === $key) {
                return $req;
            }
        }

        return null;
    }
}
