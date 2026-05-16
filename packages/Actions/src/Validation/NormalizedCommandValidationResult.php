<?php

namespace Fluxio\Actions\Validation;

class NormalizedCommandValidationResult
{
    /** @param list<string> $errors */
    private function __construct(
        public readonly bool  $valid,
        public readonly array $errors,
    ) {}

    public static function valid(): self
    {
        return new self(valid: true, errors: []);
    }

    /** @param list<string> $errors */
    public static function invalid(array $errors): self
    {
        return new self(valid: false, errors: $errors);
    }
}
