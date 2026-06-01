<?php

namespace Fluxio\Actions\Executors;

use Fluxio\Actions\Contracts\ActionExecutorInterface;
use Fluxio\Actions\DTO\Execution\ExecutionResult;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Leads\Models\Lead;
use Fluxio\Tasks\Models\Task;
use Illuminate\Validation\ValidationException;

class CreateTaskActionExecutor implements ActionExecutorInterface
{
    public function execute(ActionProposal $proposal): ExecutionResult
    {
        $payload = $proposal->changes[0]['payload'] ?? [];

        $leadId = $this->resolveLeadId($payload['lead'] ?? null);

        $task = Task::create([
            'title' => $payload['title'] ?? 'Task',
            'description' => null,
            'status' => 'pending',
            'priority' => $payload['priority'] ?? 'normal',
            'due_at' => $payload['due_at'] ?? null,
            'lead_id' => $leadId,
        ]);

        return ExecutionResult::make(
            summary: 'Task created successfully.',
            details: [
                'module' => 'tasks',
                'action' => 'created',
                'resource_type' => 'task',
                'resource_id' => $task->id,
            ],
        );
    }

    private function resolveLeadId(?string $leadName): ?int
    {
        if ($leadName === null) {
            return null;
        }

        $matches = Lead::where('name', $leadName)
            ->orWhere('company', $leadName)
            ->get();

        if ($matches->count() >= 2) {
            throw ValidationException::withMessages([
                'lead' => [__('actions::actions.ambiguous_lead')],
            ]);
        }

        return $matches->first()?->id;
    }
}
