<?php

namespace Fluxio\Actions\Diagnostics\Refinement\Exceptions;

use RuntimeException;

/**
 * Raised when the refinement evaluation corpus cannot be loaded: the file is
 * missing, the JSON is invalid, the root is not an array, or a case is missing a
 * required field (id, initial_command, refinement_text, expected) or has a
 * malformed expected block.
 *
 * Diagnostics-only. An invalid corpus aborts evaluation explicitly; this is the
 * single exception type used for every corpus-validation failure so callers can
 * branch on one class. Mirrors InvalidInterpretationCorpusException.
 */
class InvalidRefinementCorpusException extends RuntimeException {}
