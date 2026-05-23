<?php

namespace Fluxio\Actions\Llm\Exceptions;

use RuntimeException;

/**
 * Transport-level failure when calling an LLM provider.
 *
 * Covers: connection failures, timeouts, non-2xx HTTP responses, provider
 * unavailability. These are infrastructure errors, not semantic errors —
 * the response body, if any, is opaque at this layer.
 *
 * This exception is not mapped to proposal lifecycle behaviour and is not
 * rendered by ApiExceptionRenderer in Phase 2; it is meant to be caught
 * (or surfaced) by future LLM-backed interpretation providers.
 */
class LlmTransportException extends RuntimeException
{
}
