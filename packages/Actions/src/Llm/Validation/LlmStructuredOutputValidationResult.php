<?php

namespace Fluxio\Actions\Llm\Validation;

/**
 * Result of LlmStructuredOutputValidator::validate().
 *
 * Mirrors the shape of NormalizedCommandValidationResult so callers can
 * branch the same way at both validation boundaries.
 */
final readonly class LlmStructuredOutputValidationResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public bool  $valid,
        public array $errors = [],
    ) {}

    public static function valid(): self
    {
        return new self(true, []);
    }

    /**
     * @param list<string> $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(false, $errors);
    }
}
