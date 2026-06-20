<?php

namespace Fluxio\Actions\Executors;

use Fluxio\Actions\Contracts\ActionExecutorInterface;
use Fluxio\Actions\DTO\Execution\ExecutionResult;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Leads\Models\Lead;
use Illuminate\Validation\ValidationException;

/**
 * Executes a confirmed update_lead_status proposal: re-resolves the target Lead by its
 * committed name/company, validates the target status against the Lead domain, and
 * persists the new status.
 *
 * Mirrors AssignLeadActionExecutor / UpdateTaskStatusActionExecutor: it re-resolves
 * from the proposal's editable_fields (resolved display values, never ids) so the
 * proposal/provider surface stays id-free, and it fails safely with a
 * ValidationException when the lead or status is missing, unknown, or ambiguous — the
 * runtime treats that as a 422 with the proposal left confirmed, not an execution
 * failure. No transition rules are applied; Lead::STATUSES is the final safety net.
 */
class UpdateLeadStatusActionExecutor implements ActionExecutorInterface
{
    public function execute(ActionProposal $proposal): ExecutionResult
    {
        $fields = collect($proposal->editable_fields ?? [])->keyBy('key');

        $leadValue = $fields->get('lead')['value'] ?? null;
        $stateValue = $fields->get('state')['value'] ?? null;

        $lead = $this->resolveLead($leadValue);
        $status = $this->validateStatus($stateValue);

        $lead->update(['status' => $status]);

        return ExecutionResult::make(
            summary: 'Lead status updated successfully.',
            details: [
                'type' => 'lead_status_updated',
                'status' => $status,
                'lead' => $lead->name,
                'lead_id' => $lead->id,
            ],
        );
    }

    private function resolveLead(?string $leadValue): Lead
    {
        if ($leadValue === null) {
            throw ValidationException::withMessages([
                'lead' => [__('actions::actions.unknown_lead')],
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

    private function validateStatus(?string $statusValue): string
    {
        if ($statusValue === null || ! in_array($statusValue, Lead::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => [__('actions::actions.invalid_lead_status')],
            ]);
        }

        return $statusValue;
    }
}
