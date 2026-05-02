<?php

namespace Fluxio\Actions\DTO;

class ParsedIntent
{
    public function __construct(
        public readonly string $intent,
        public readonly array $entities = [],
    ) {}
}
