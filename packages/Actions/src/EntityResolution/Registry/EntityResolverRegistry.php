<?php

namespace Fluxio\Actions\EntityResolution\Registry;

use Fluxio\Actions\EntityResolution\Contracts\EntityResolverInterface;
use Fluxio\Actions\EntityResolution\DTO\ResolutionContext;
use Fluxio\Actions\EntityResolution\DTO\ResolutionResult;

class EntityResolverRegistry
{
    /** @var EntityResolverInterface[] */
    private array $resolvers = [];

    public function register(EntityResolverInterface $resolver): void
    {
        $this->resolvers[] = $resolver;
    }

    public function supports(string $entityType): bool
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($entityType)) {
                return true;
            }
        }

        return false;
    }

    public function resolve(string $query, ResolutionContext $context): ResolutionResult
    {
        foreach ($this->resolvers as $resolver) {
            if ($resolver->supports($context->entityType)) {
                return $resolver->resolve($query, $context);
            }
        }

        return ResolutionResult::noMatch();
    }
}
