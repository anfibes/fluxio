<?php

namespace Fluxio\Actions\Executors;

use Fluxio\Actions\Contracts\ActionExecutorInterface;
use Fluxio\Actions\DTO\Execution\ExecutionResult;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Leads\Models\Lead;
use Illuminate\Validation\ValidationException;

/**
 * Executes a confirmed update_lead_status proposal: loads the target Lead through
 * the identity-continuity contract, validates the target status against the Lead
 * domain, and persists the new status.
 *
 * Identity continuity (slice 1 — this is the first executor consuming it):
 * proposals built under the contract (`resolved_entities` is an array) carry the
 * server-owned identity of the resolved lead in `resolved_entities['lead']`, and
 * the executor acts on that primary key. The label is a presentation snapshot and
 * is never compared — a lead renamed after the proposal still executes against the
 * same row, and homonymous leads execute against exactly the candidate the user
 * selected. When the identity is missing (never resolved, invalidated, or the
 * structure is invalid) or the row no longer exists, execution fails safely with a
 * ValidationException — a 422 with the proposal left confirmed, not an execution
 * failure.
 *
 * Legacy fallback: only a proposal persisted BEFORE the contract existed
 * (`resolved_entities === null`) is re-resolved by name/company as before. An
 * empty map (`[]`) is a contract-bearing proposal without a resolved lead and
 * must NOT fall back to labels. The fallback is isolated in
 * resolveLeadByLabelLegacy() so it can be removed once legacy proposals age out.
 */
class UpdateLeadStatusActionExecutor implements ActionExecutorInterface
{
    public function execute(ActionProposal $proposal): ExecutionResult
    {
        $fields = collect($proposal->editable_fields ?? [])->keyBy('key');

        $stateValue = $fields->get('state')['value'] ?? null;

        $lead = $proposal->resolved_entities === null
            ? $this->resolveLeadByLabelLegacy($fields->get('lead')['value'] ?? null)
            : $this->resolveLeadByIdentity($proposal->resolved_entities['lead'] ?? null);

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

    /**
     * Load the lead by its server-owned identity (primary key). The label inside
     * the entry is never consulted. A missing entry, a malformed entry, or a row
     * that no longer exists all fail the same way: safe validation error, proposal
     * stays confirmed.
     */
    private function resolveLeadByIdentity(mixed $entry): Lead
    {
        $id = is_array($entry) ? ($entry['id'] ?? null) : null;

        if (! is_int($id) && ! (is_string($id) && $id !== '')) {
            throw ValidationException::withMessages([
                'lead' => [__('actions::actions.unknown_lead')],
            ]);
        }

        $lead = Lead::find($id);

        if ($lead === null) {
            throw ValidationException::withMessages([
                'lead' => [__('actions::actions.unknown_lead')],
            ]);
        }

        return $lead;
    }

    /**
     * LEGACY fallback — textual re-resolution by name/company, kept only for
     * proposals persisted before the identity-continuity contract
     * (resolved_entities === null). Do not extend; scheduled for removal.
     */
    private function resolveLeadByLabelLegacy(?string $leadValue): Lead
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
