<?php

namespace Fluxio\Actions\Llm\Exceptions;

use RuntimeException;

/**
 * Malformed LLM response that cannot be normalized into a generic LlmResponse.
 *
 * Covers: missing response field, non-string response payload, invalid JSON
 * when structured output was requested, or any structural failure preventing
 * the client from returning a usable LlmResponse.
 *
 * This is a structural transport error, not a Fluxio business validation
 * error: domain checks (allowed intents, allowed entity keys, confidence
 * range) remain the responsibility of NormalizedCommandValidator and the
 * future LLM-backed interpretation provider.
 */
class InvalidLlmResponseException extends RuntimeException {}
