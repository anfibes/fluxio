<?php

namespace Fluxio\Actions\Executors;

use Fluxio\Actions\Contracts\ActionExecutorInterface;
use Fluxio\Actions\DTO\Execution\ExecutionResult;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Tasks\Models\Task;
use Illuminate\Validation\ValidationException;

/**
 * Executes a confirmed update_task_status proposal: loads the target Task through
 * the identity-continuity contract, validates the target status against the Task
 * domain, and persists the new status.
 *
 * Identity continuity (slice 2 — mirrors UpdateLeadStatusActionExecutor):
 * proposals built under the contract (`resolved_entities` is an array) carry the
 * server-owned identity of the resolved task in `resolved_entities['task']`, and
 * the executor acts on that primary key. The label is a presentation snapshot and
 * is never compared — a task renamed after the proposal still executes against
 * the same row, and homonymous titles execute against exactly the candidate the
 * user selected. When the identity is missing (never resolved or malformed) or
 * the row no longer exists, execution fails safely with a ValidationException —
 * a 422 with the proposal left confirmed, not an execution failure.
 *
 * Legacy fallback: only a proposal persisted BEFORE the contract existed
 * (`resolved_entities === null`) is re-resolved by title as before. An empty map
 * (`[]`) or a map without a `task` entry is a contract-bearing proposal without a
 * resolved task and must NOT fall back to the title. The fallback is isolated in
 * resolveTaskByTitleLegacy() so it can be removed once legacy proposals age out.
 */
class UpdateTaskStatusActionExecutor implements ActionExecutorInterface
{
    public function execute(ActionProposal $proposal): ExecutionResult
    {
        $fields = collect($proposal->editable_fields ?? [])->keyBy('key');

        $statusValue = $fields->get('state')['value'] ?? null;

        $task = $proposal->resolved_entities === null
            ? $this->resolveTaskByTitleLegacy($fields->get('task')['value'] ?? null)
            : $this->resolveTaskByIdentity($proposal->resolved_entities['task'] ?? null);

        $status = $this->validateStatus($statusValue);

        $task->update(['status' => $status]);

        return ExecutionResult::make(
            summary: 'Task status updated successfully.',
            details: [
                'type' => 'task_status_updated',
                'status' => $status,
                'task' => $task->title,
                'task_id' => $task->id,
            ],
        );
    }

    /**
     * Load the task by its server-owned identity (primary key). The label inside
     * the entry is never consulted. A missing entry, a malformed entry, or a row
     * that no longer exists all fail the same way: safe validation error, proposal
     * stays confirmed.
     */
    private function resolveTaskByIdentity(mixed $entry): Task
    {
        $id = is_array($entry) ? ($entry['id'] ?? null) : null;

        if (! is_int($id) && ! (is_string($id) && $id !== '')) {
            throw ValidationException::withMessages([
                'task' => [__('actions::actions.unknown_task')],
            ]);
        }

        $task = Task::find($id);

        if ($task === null) {
            throw ValidationException::withMessages([
                'task' => [__('actions::actions.unknown_task')],
            ]);
        }

        return $task;
    }

    /**
     * LEGACY fallback — textual re-resolution by title, kept only for proposals
     * persisted before the identity-continuity contract
     * (resolved_entities === null). Do not extend; scheduled for removal.
     */
    private function resolveTaskByTitleLegacy(?string $taskValue): Task
    {
        if ($taskValue === null) {
            throw ValidationException::withMessages([
                'task' => [__('actions::actions.unknown_task')],
            ]);
        }

        $matches = Task::where('title', $taskValue)->get();

        if ($matches->count() === 0) {
            throw ValidationException::withMessages([
                'task' => [__('actions::actions.unknown_task')],
            ]);
        }

        if ($matches->count() >= 2) {
            throw ValidationException::withMessages([
                'task' => [__('actions::actions.ambiguous_task')],
            ]);
        }

        return $matches->first();
    }

    private function validateStatus(?string $statusValue): string
    {
        if ($statusValue === null || ! in_array($statusValue, Task::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => [__('actions::actions.invalid_task_status')],
            ]);
        }

        return $statusValue;
    }
}
