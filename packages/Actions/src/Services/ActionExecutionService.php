<?php

namespace Fluxio\Actions\Services;

use Fluxio\Actions\Contracts\ActionExecutorInterface;
use Fluxio\Actions\Executors\CreateTaskActionExecutor;
use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ActionExecutionService
{
    public function __construct(
        private readonly CreateTaskActionExecutor $createTaskExecutor,
    ) {}

    public function execute(ActionProposal $proposal): ActionProposal
    {
        if ($proposal->status === 'executed') {
            return $proposal;
        }

        if ($proposal->status !== 'confirmed') {
            throw ValidationException::withMessages([
                'proposal' => [__('actions::actions.cannot_execute')],
            ]);
        }

        try {
            DB::transaction(function () use ($proposal) {
                $executor = $this->resolveExecutor($proposal->intent);
                $result = $executor->execute($proposal);
                $proposal->forceFill([
                    'status' => 'executed',
                    'executed_at' => now(),
                    'execution_result' => $result,
                ])->save();
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $proposal->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => $e->getMessage(),
            ])->save();
            throw $e;
        }

        return $proposal->refresh();
    }

    private function resolveExecutor(string $intent): ActionExecutorInterface
    {
        return match ($intent) {
            'create_task' => $this->createTaskExecutor,
            default => throw new \RuntimeException("No executor registered for intent [{$intent}]."),
        };
    }
}
