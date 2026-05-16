<?php

namespace Fluxio\Actions\EntityResolution\DTO;

class ResolutionContext
{
    public function __construct(
        public readonly string $entityType,
        public readonly string $locale = 'en',
    ) {}
}
