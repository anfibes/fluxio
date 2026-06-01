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
 * effectful but gated. It is the only authority allowed to produce execution-time
 * domain side effects, and it does so under a single invariant:
 *
 *   An action executes at most once, only from a confirmed proposal, only over
 *   committed proposal state, with no reinterpretation and no ambiguity reopening,
 *   under an atomic proposal-lifecycle guard, terminating in exactly one outcome —
 *   executed + typed result, or failed + typed failure.
 *
 * The proposal-lifecycle boundary is guarded atomically: the proposal row is locked
 * (lockForUpdate) and its status re-checked inside the transaction, so two
 * concurrent executes can never both run an executor. Current DB-backed executors
 * run inside that same transaction, so their domain side effects are atomic with
 * the lifecycle transition.
 *
 * The terminal state — executed OR failed — is always persisted while the row lock
 * is still held. A failing executor's partial side effects are rolled back (via an
 * inner savepoint), but the `failed` terminal state is committed under the same
 * lock before it is released, closing the race where a second concurrent request
 * could observe `confirmed` after a rollback and run the executor again. Failures
 * are typed and sanitized — a raw exception message is never persisted into
 * proposal state.
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

        // Captures an unexpected executor throwable so it can be re-raised AFTER the
        // outer transaction commits the `failed` terminal state — re-throwing inside
        // the closure would roll the failed write back with the rest of the txn.
        $deferred = null;

        DB::transaction(function () use ($proposal, &$deferred): void {
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

            try {
                // Inner savepoint: the executor's domain side effects and the
                // `executed` write commit together, or roll back together, without
                // discarding the `failed` write persisted on the outer transaction.
                DB::transaction(function () use ($locked): void {
                    $executor = $this->resolveExecutor($locked->intent);
                    $result = $executor->execute($locked);

                    $locked->forceFill([
                        'status' => 'executed',
                        'executed_at' => now(),
                        'execution_result' => $result->toArray(),
                    ])->save();
                });
            } catch (ValidationException $e) {
                // Validation (e.g. an ambiguous lead at execution time) is not an
                // execution failure: roll the whole turn back and surface a 422 with
                // the proposal still `confirmed` and no failure recorded.
                throw $e;
            } catch (\Throwable $e) {
                // The inner savepoint already rolled back any partial side effects.
                // Persist the terminal `failed` state on the SAME outer transaction
                // and lock, then defer the re-throw so the outer commit keeps it.
                $failure = $this->toExecutionFailure($locked->intent);

                $locked->forceFill([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'failure_reason' => $failure->message,
                    'failure_reason_code' => $failure->reason->value,
                ])->save();

                $deferred = $e;
            }
        });

        if ($deferred !== null) {
            throw $deferred;
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
