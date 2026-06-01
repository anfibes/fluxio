<?php

namespace Fluxio\Actions\Executors;

use Fluxio\Actions\Contracts\ActionExecutorInterface;
use Fluxio\Actions\DTO\Execution\ExecutionResult;
use Fluxio\Actions\Models\ActionProposal;

class AssignLeadActionExecutor implements ActionExecutorInterface
{
    public function execute(ActionProposal $proposal): ExecutionResult
    {
        $fields = collect($proposal->editable_fields ?? [])->keyBy('key');

        return ExecutionResult::make(
            summary: 'Lead assigned successfully.',
            details: [
                'type' => 'lead_assigned',
                'status' => 'assigned',
                'lead' => $fields->get('lead')['value'] ?? null,
                'assignee' => $fields->get('assignee')['value'] ?? null,
            ],
        );
    }
}
