<?php

namespace Fluxio\Actions\Executors;

use App\Models\User;
use Fluxio\Actions\Contracts\ActionExecutorInterface;
use Fluxio\Actions\DTO\Execution\ExecutionResult;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Leads\Models\Lead;
use Illuminate\Validation\ValidationException;

class AssignLeadActionExecutor implements ActionExecutorInterface
{
    public function execute(ActionProposal $proposal): ExecutionResult
    {
        $fields = collect($proposal->editable_fields ?? [])->keyBy('key');

        $leadValue    = $fields->get('lead')['value']     ?? null;
        $assigneeValue = $fields->get('assignee')['value'] ?? null;

        $lead = $this->resolveLead($leadValue);
        $user = $this->resolveUser($assigneeValue);

        $lead->update([
            'assigned_to_user_id' => $user->id,
            'assigned_at'         => now(),
        ]);

        return ExecutionResult::make(
            summary: 'Lead assigned successfully.',
            details: [
                'type'     => 'lead_assigned',
                'status'   => 'assigned',
                'lead'     => $lead->name,
                'lead_id'  => $lead->id,
                'assignee' => $user->name,
                'user_id'  => $user->id,
            ],
        );
    }

    private function resolveLead(?string $leadValue): Lead
    {
        if ($leadValue === null) {
            throw ValidationException::withMessages([
                'lead' => [__('actions::actions.ambiguous_lead')],
            ]);
        }

        $matches = Lead::where('name', $leadValue)
            ->orWhere('company', $leadValue)
            ->get();

        if ($matches->count() === 0) {
            throw ValidationException::withMessages([
                'lead' => [__('actions::actions.unknown_lead')],
            ]);
        }

        if ($matches->count() >= 2) {
            throw ValidationException::withMessages([
                'lead' => [__('actions::actions.ambiguous_lead')],
            ]);
        }

        return $matches->first();
    }

    private function resolveUser(?string $assigneeValue): User
    {
        if ($assigneeValue === null) {
            throw ValidationException::withMessages([
                'assignee' => [__('actions::actions.unknown_assignee')],
            ]);
        }

        $matches = User::where('name', $assigneeValue)->get();

        if ($matches->count() === 0) {
            throw ValidationException::withMessages([
                'assignee' => [__('actions::actions.unknown_assignee')],
            ]);
        }

        if ($matches->count() >= 2) {
            throw ValidationException::withMessages([
                'assignee' => [__('actions::actions.ambiguous_assignee')],
            ]);
        }

        return $matches->first();
    }
}
