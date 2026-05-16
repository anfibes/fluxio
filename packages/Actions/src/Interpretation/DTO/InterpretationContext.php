<?php

namespace Fluxio\Actions\Interpretation\DTO;

class InterpretationContext
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $locale   = 'en',
        public readonly ?int   $userId   = null,
        public readonly array  $metadata = [],
    ) {}
}
