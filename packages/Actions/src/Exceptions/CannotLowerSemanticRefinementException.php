<?php

namespace Fluxio\Actions\Exceptions;

use RuntimeException;

/**
 * Raised when a SemanticRefinementMutation cannot be deterministically lowered
 * into a structural NormalizedMutation: the semantic type is not supported by
 * the lowerer (e.g. Unknown, or an ambiguity operation not yet modeled), or its
 * payload is malformed/incomplete for the requested type.
 *
 * Lowering must never silently fall back: an un-lowerable semantic operation
 * must NOT reach the application boundary. This is the single failure type for
 * the semantic → structural lowering boundary so callers can branch on one
 * class.
 */
class CannotLowerSemanticRefinementException extends RuntimeException {}
