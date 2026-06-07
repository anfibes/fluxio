<?php

namespace Fluxio\Actions\Examples\Exceptions;

use RuntimeException;

/**
 * Raised when an intent-examples file cannot be loaded: the file is missing, the
 * JSON is invalid, the root is not a list, a record is missing a required field
 * (id, locale, intent, and at least one of text/template), an id is duplicated, the
 * record locale does not match the file's locale, the intent is unknown, a
 * slot/placeholder does not map to an authoritative requirement or parser key, or a
 * forbidden runtime/authority field appears.
 *
 * Fail-closed: any malformation aborts loading. This is the single exception type
 * for every intent-examples validation failure, mirroring the diagnostics corpus
 * loaders, so callers can branch on one class.
 */
class InvalidIntentExampleException extends RuntimeException {}
