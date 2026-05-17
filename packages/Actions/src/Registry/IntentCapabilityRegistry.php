<?php

namespace Fluxio\Actions\Registry;

use Fluxio\Actions\DTO\IntentCapability;
use Fluxio\Actions\DTO\NormalizedMutation;

class IntentCapabilityRegistry
{
    /** @var array<string, IntentCapability> */
    private array $capabilities = [];

    public function register(IntentCapability $capability): void
    {
        $this->capabilities[$capability->intent] = $capability;
    }

    public function find(string $intent): ?IntentCapability
    {
        return $this->capabilities[$intent] ?? null;
    }

    /**
     * @throws \RuntimeException when no capability is registered for the intent
     */
    public function mustFind(string $intent): IntentCapability
    {
        $capability = $this->find($intent);

        if ($capability === null) {
            throw new \RuntimeException("No capability registered for intent [{$intent}].");
        }

        return $capability;
    }

    /**
     * Determine whether a NormalizedMutation is allowed for the given intent.
     *
     * Rules:
     * - If no capability is registered for the intent, default to deny
     *   (conservative: unlisted intents have no explicit mutation contract).
     * - A mutation is allowed when a MutationCapability exists whose:
     *   - operation matches the mutation's operation
     *   - fields list contains the mutation's field
     *   - collection flag matches whether this is a collection mutation
     *     (collection mutations have operation='append'/'remove', or
     *      operation='replace' with a non-null target)
     */
    public function allowsMutation(string $intent, NormalizedMutation $mutation): bool
    {
        $capability = $this->find($intent);

        if ($capability === null) {
            return false;
        }

        $isCollectionMutation = $this->isCollectionMutation($mutation);

        foreach ($capability->mutations as $mutCap) {
            if (
                $mutCap->operation === $mutation->operation
                && in_array($mutation->field, $mutCap->fields, true)
                && $mutCap->collection === $isCollectionMutation
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * A mutation is a collection mutation when it uses a collection-level operation
     * (append, remove) or is a targeted replace (replace with a non-null target).
     */
    private function isCollectionMutation(NormalizedMutation $mutation): bool
    {
        if (in_array($mutation->operation, ['append', 'remove'], true)) {
            return true;
        }

        if ($mutation->operation === 'replace' && $mutation->target !== null) {
            return true;
        }

        return false;
    }
}
