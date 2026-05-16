<?php

namespace Fluxio\Actions\Executors;

use Fluxio\Actions\Contracts\ActionExecutorInterface;
use Fluxio\Actions\Models\ActionProposal;

class AssignLeadActionExecutor implements ActionExecutorInterface
{
    public function execute(ActionProposal $proposal): array
    {
        $fields = collect($proposal->editable_fields ?? [])->keyBy('key');

        return [
            'type'     => 'lead_assigned',
            'status'   => 'assigned',
            'lead'     => $fields->get('lead')['value'] ?? null,
            'assignee' => $fields->get('assignee')['value'] ?? null,
            'message'  => 'Lead assigned successfully.',
        ];
    }
}
