<?php

namespace Fluxio\Actions\Contracts;

use Fluxio\Actions\DTO\Execution\ExecutionResult;
use Fluxio\Actions\Models\ActionProposal;

interface ActionExecutorInterface
{
    /**
     * Apply the action's domain side effect over the committed proposal state and
     * return the typed execution result. Executors do not reinterpret text, reopen
     * ambiguity, or inspect provider metadata — they consume committed proposal
     * state only and run inside the execution authority's atomic transaction.
     */
    public function execute(ActionProposal $proposal): ExecutionResult;
}
