<?php

namespace Fluxio\Actions\Registry;

use Fluxio\Actions\Contracts\ActionExecutorInterface;
use Fluxio\Actions\DTO\IntentDefinition;

class IntentRegistry
{
    /** @var array<string, IntentDefinition> */
    private array $definitions = [];

    public function register(IntentDefinition $definition): void
    {
        $this->definitions[$definition->intent] = $definition;
    }

    public function find(string $intent): ?IntentDefinition
    {
        return $this->definitions[$intent] ?? null;
    }

    /** @return array<string, IntentDefinition> */
    public function all(): array
    {
        return $this->definitions;
    }

    public function resolveExecutor(string $intent): ActionExecutorInterface
    {
        $definition = $this->find($intent);

        if ($definition === null) {
            throw new \RuntimeException("No executor registered for intent [{$intent}].");
        }

        return app($definition->executorClass);
    }
}
