<?php

namespace Fluxio\Actions\Executors;

use Fluxio\Actions\Contracts\ActionExecutorInterface;
use Fluxio\Actions\DTO\Execution\ExecutionResult;
use Fluxio\Actions\Models\ActionProposal;

class ScheduleMeetingActionExecutor implements ActionExecutorInterface
{
    public function execute(ActionProposal $proposal): ExecutionResult
    {
        $fields = collect($proposal->editable_fields ?? [])->keyBy('key');

        return ExecutionResult::make(
            summary: 'Meeting scheduled successfully.',
            details: [
                'type' => 'meeting_scheduled',
                'status' => 'scheduled',
                'lead' => $fields->get('lead')['value'] ?? null,
                'date' => $fields->get('date')['value'] ?? null,
                'time' => $fields->get('time')['value'] ?? null,
            ],
        );
    }
}
