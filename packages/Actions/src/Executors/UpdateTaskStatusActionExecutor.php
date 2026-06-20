<?php

namespace Fluxio\Actions\Executors;

use Fluxio\Actions\Contracts\ActionExecutorInterface;
use Fluxio\Actions\DTO\Execution\ExecutionResult;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Tasks\Models\Task;
use Illuminate\Validation\ValidationException;

/**
 * Executes a confirmed update_task_status proposal: re-resolves the target Task by its
 * committed title, validates the target status against the Task domain, and persists
 * the new status.
 *
 * Like AssignLeadActionExecutor, it re-resolves from the proposal's editable_fields
 * (resolved display values, never ids) so the proposal/provider surface stays
 * id-free, and it fails safely with a ValidationException when the task or status is
 * missing, unknown, or ambiguous — the runtime treats that as a 422 with the proposal
 * left confirmed, not an execution failure.
 */
class UpdateTaskStatusActionExecutor implements ActionExecutorInterface
{
    public function execute(ActionProposal $proposal): ExecutionResult
    {
        $fields = collect($proposal->editable_fields ?? [])->keyBy('key');

        $taskValue = $fields->get('task')['value'] ?? null;
        $statusValue = $fields->get('state')['value'] ?? null;

        $task = $this->resolveTask($taskValue);
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

    private function resolveTask(?string $taskValue): Task
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
