<?php

namespace Fluxio\Actions\Services;

use Fluxio\Actions\Contracts\ActionExecutorInterface;
use Fluxio\Actions\DTO\Execution\ExecutionFailure;
use Fluxio\Actions\Enums\ExecutionFailureReason;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Actions\Registry\IntentRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Execution Runtime v1 — the third deterministic runtime authority.
 *
 * Field-state and ambiguity-state are pure over proposal state; this authority is
 * effectful but gated. It is the ONLY authority allowed to produce external side
 * effects, and it does so under a single invariant:
 *
 *   An action executes at most once, only from a confirmed proposal, only over
 *   committed proposal state, with no reinterpretation and no ambiguity reopening,
 *   atomically, terminating in exactly one outcome — executed + typed result, or
 *   failed + typed failure.
 *
 * The guard is atomic: the proposal row is locked (lockForUpdate) and its status
 * re-checked inside the transaction, so two concurrent executes can never both
 * run an executor. Failures are typed and sanitized — a raw exception message is
 * never persisted into proposal state.
 */
class ActionExecutionService
{
    public function __construct(
        private readonly IntentRegistry $registry,
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
            DB::transaction(function () use ($proposal): void {
                /** @var ActionProposal|null $locked */
                $locked = ActionProposal::query()
                    ->whereKey($proposal->getKey())
                    ->lockForUpdate()
                    ->first();

                // Re-evaluate the guard under the row lock: a concurrent request may
                // have executed first, or moved the proposal out of `confirmed`.
                if ($locked === null || $locked->status === 'executed') {
                    return;
                }

                if ($locked->status !== 'confirmed') {
                    throw ValidationException::withMessages([
                        'proposal' => [__('actions::actions.cannot_execute')],
                    ]);
                }

                $executor = $this->resolveExecutor($locked->intent);
                $result = $executor->execute($locked);

                $locked->forceFill([
                    'status' => 'executed',
                    'executed_at' => now(),
                    'execution_result' => $result->toArray(),
                ])->save();
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $failure = $this->toExecutionFailure($proposal->intent);

            $proposal->forceFill([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => $failure->message,
            ])->save();

            throw $e;
        }

        return $proposal->refresh();
    }

    private function resolveExecutor(string $intent): ActionExecutorInterface
    {
        return $this->registry->resolveExecutor($intent);
    }

    /**
     * Map an execution throwable onto the closed failure taxonomy, with a
     * sanitized, localized message. The underlying exception message is never
     * persisted into proposal state.
     */
    private function toExecutionFailure(string $intent): ExecutionFailure
    {
        if ($this->registry->find($intent) === null) {
            return ExecutionFailure::unsupportedIntent(
                __(ExecutionFailureReason::UnsupportedIntent->messageKey()),
            );
        }

        return ExecutionFailure::executionFailed(
            __(ExecutionFailureReason::ExecutionFailed->messageKey()),
        );
    }
}
