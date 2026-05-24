<?php

namespace Fluxio\Actions\Diagnostics\Evaluation\DTO;

/**
 * One versioned evaluation example. Built by InterpretationCorpusLoader from the
 * corpus JSON. `expectedEntities` is partial and exact-match only — it lists the
 * keys that must match, not the full parser output.
 */
final class InterpretationCorpusCase
{
    /**
     * @param  array<string, mixed>  $expectedEntities
     * @param  list<string>  $notes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $text,
        public readonly string $locale,
        public readonly string $expectedIntent,
        public readonly array $expectedEntities = [],
        public readonly array $notes = [],
    ) {}
}
