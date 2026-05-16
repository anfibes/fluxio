<?php

namespace Fluxio\Actions\EntityResolution\DTO;

class ResolutionResult
{
    public readonly bool $resolved;

    private function __construct(
        public readonly bool                 $autoResolved,
        public readonly ?ResolutionCandidate $resolvedCandidate,
        /** @var ResolutionCandidate[] */
        public readonly array                $candidates,
    ) {
        $this->resolved = $autoResolved && $resolvedCandidate !== null;
    }

    public static function autoResolved(ResolutionCandidate $candidate): self
    {
        return new self(autoResolved: true, resolvedCandidate: $candidate, candidates: []);
    }

    /** @param ResolutionCandidate[] $candidates */
    public static function ambiguous(array $candidates): self
    {
        return new self(autoResolved: false, resolvedCandidate: null, candidates: $candidates);
    }

    public static function noMatch(): self
    {
        return new self(autoResolved: false, resolvedCandidate: null, candidates: []);
    }
}
