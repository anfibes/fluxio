<?php

namespace Fluxio\Actions\DTO;

class EntityRequirement
{
    public function __construct(
        public readonly string $key,
        public readonly string $entityType,
        public readonly string $label,
        public readonly bool   $required,
        public readonly string $cardinality     = 'one',
        public readonly bool   $resolverRequired = false,
    ) {}
}
