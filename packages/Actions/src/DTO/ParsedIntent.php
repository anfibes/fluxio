<?php

namespace Fluxio\Actions\DTO;

class ParsedIntent
{
    /**
     * @param  array<string, mixed>  $entities
     * @param  string[]  $warnings  Non-fatal interpretation notes (e.g. inferred day-part times)
     */
    public function __construct(
        public readonly string $intent,
        public readonly array $entities = [],
        public readonly array $warnings = [],
    ) {}
}
