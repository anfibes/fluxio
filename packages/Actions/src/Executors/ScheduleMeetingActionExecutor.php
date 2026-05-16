<?php

namespace Fluxio\Actions\Executors;

use Fluxio\Actions\Contracts\ActionExecutorInterface;
use Fluxio\Actions\Models\ActionProposal;

class ScheduleMeetingActionExecutor implements ActionExecutorInterface
{
    public function execute(ActionProposal $proposal): array
    {
        $fields = collect($proposal->editable_fields ?? [])->keyBy('key');

        return [
            'type'    => 'meeting_scheduled',
            'status'  => 'scheduled',
            'lead'    => $fields->get('lead')['value'] ?? null,
            'date'    => $fields->get('date')['value'] ?? null,
            'time'    => $fields->get('time')['value'] ?? null,
            'message' => 'Meeting scheduled successfully.',
        ];
    }
}
