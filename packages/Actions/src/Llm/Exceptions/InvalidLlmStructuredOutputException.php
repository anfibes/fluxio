<?php

namespace Fluxio\Actions\Llm\Exceptions;

use RuntimeException;

/**
 * Raised when parsed LLM JSON violates the structured-output contract that
 * a future LLM-backed interpretation provider must satisfy *before* any
 * conversion to NormalizedCommand.
 *
 * This is a semantic-contract violation (forbidden keys, unknown intent,
 * incompatible entity keys, …), not a transport-level failure. Transport
 * failures continue to use LlmTransportException / InvalidLlmResponseException.
 *
 * It does not replace NormalizedCommandValidator: this exception fires
 * before NormalizedCommand is built; NormalizedCommandValidator continues
 * to guard the proposal pipeline downstream.
 */
class InvalidLlmStructuredOutputException extends RuntimeException
{
    /**
     * @param  list<string>  $errors
     */
    public function __construct(public readonly array $errors, ?string $message = null)
    {
        parent::__construct(
            $message ?? 'LLM structured output is invalid: '.implode('; ', $errors),
        );
    }
}
