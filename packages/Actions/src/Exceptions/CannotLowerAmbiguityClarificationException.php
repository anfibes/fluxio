<?php

namespace Fluxio\Actions\Exceptions;

use RuntimeException;

/**
 * Raised when a SemanticAmbiguityClarification cannot be lowered into an
 * AmbiguityDirective: a missing ambiguity key, a malformed selector (e.g. a
 * non-positive ordinal, an empty label, an empty attribute dimension/value), or
 * an attribute selector that targets a forbidden identity dimension (which would
 * be a candidate-id backdoor).
 *
 * Lowering validates FORM only and must never silently pass a malformed
 * clarification through. A well-formed clarification that simply matches no
 * candidate is NOT a lowering error — that is a runtime outcome computed by
 * AmbiguityResolver (Unresolved).
 */
class CannotLowerAmbiguityClarificationException extends RuntimeException {}
