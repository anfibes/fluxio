<?php

namespace Fluxio\Actions\Diagnostics\Evaluation\Exceptions;

use RuntimeException;

/**
 * Raised when the interpretation evaluation corpus cannot be loaded: the file is
 * missing, the JSON is invalid, the root is not an array, or a case is missing a
 * required field (id, text, expected.intent) or has a malformed expected.entities.
 *
 * Diagnostics-only. An invalid corpus aborts evaluation explicitly; this is the
 * single exception type used for every corpus-validation failure so callers can
 * branch on one class.
 */
class InvalidInterpretationCorpusException extends RuntimeException {}
