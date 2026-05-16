<?php

namespace Fluxio\Actions\EntityResolution\Contracts;

use Fluxio\Actions\EntityResolution\DTO\ResolutionContext;
use Fluxio\Actions\EntityResolution\DTO\ResolutionResult;

interface EntityResolverInterface
{
    public function supports(string $entityType): bool;

    public function resolve(string $query, ResolutionContext $context): ResolutionResult;
}
