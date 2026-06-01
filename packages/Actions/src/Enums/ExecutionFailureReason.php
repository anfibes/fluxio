<?php

namespace Fluxio\Actions\Enums;

/**
 * The closed taxonomy of execution failures (Execution Runtime v1). An execution
 * terminates in exactly one outcome: executed + typed result, or failed + typed
 * failure. This enum is the typed `reason` of the failed outcome.
 *
 * It is deliberately small and provider-blind. A raw exception message is NEVER
 * persisted into proposal state; the service maps the underlying throwable onto
 * one of these stable reasons and persists the matching localized, sanitized
 * message instead.
 *
 *  - UnsupportedIntent : no executor is registered for the proposal's intent.
 *  - ExecutionFailed   : the executor ran but raised an (unexpected) error.
 */
enum ExecutionFailureReason: string
{
    case UnsupportedIntent = 'unsupported_intent';
    case ExecutionFailed = 'execution_failed';

    /**
     * The translation key whose message is safe to persist and display.
     */
    public function messageKey(): string
    {
        return 'actions::actions.execution_failure.'.$this->value;
    }
}
