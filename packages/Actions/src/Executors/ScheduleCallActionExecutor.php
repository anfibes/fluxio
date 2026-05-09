<?php

namespace Fluxio\Actions\Executors;

use Fluxio\Actions\Contracts\ActionExecutorInterface;
use Fluxio\Actions\Models\ActionProposal;

class ScheduleCallActionExecutor implements ActionExecutorInterface
{
    public function execute(ActionProposal $proposal): array
    {
        $fields = collect($proposal->editable_fields ?? [])->keyBy('key');

        $lead = $fields->get('lead')['value'] ?? ($proposal->entities['lead'] ?? null);
        $date = $fields->get('date')['value'] ?? null;
        $time = $fields->get('time')['value'] ?? null;

        return [
            'type' => 'scheduled_call',
            'status' => 'scheduled',
            'lead' => $lead,
            'date' => $date,
            'time' => $time,
            'message' => 'Call scheduled successfully.',
        ];
    }
}
