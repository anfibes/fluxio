<?php

namespace Fluxio\Actions\DTO;

class NormalizedCommand
{
    /**
     * @param array<string, mixed> $entities  Extracted entities (lead, lead_query, …)
     * @param string[]             $warnings  Non-fatal interpretation notes
     */
    public function __construct(
        public readonly string $intent,
        public readonly float  $confidence,
        public readonly string $sourceText,
        public readonly string $locale,
        public readonly array  $entities = [],
        public readonly array  $warnings = [],
    ) {}
}
