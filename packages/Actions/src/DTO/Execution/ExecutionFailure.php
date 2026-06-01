<?php

namespace Fluxio\Actions\DTO\Execution;

use Fluxio\Actions\Enums\ExecutionFailureReason;

/**
 * The typed failure of an action execution (Execution Runtime v1).
 *
 * It is the sole producer of what gets persisted into `failure_reason`: a stable
 * `reason` drawn from a closed taxonomy plus a sanitized, display-safe `message`.
 * A raw exception message never reaches proposal state — the service maps the
 * underlying throwable onto one of these typed failures and persists its message.
 *
 * Like ExecutionResult, it is inert reporting data: provider-blind (it names a
 * deterministic terminal outcome, not who produced the interpretation) and never
 * read back by the runtime.
 */
final class ExecutionFailure
{
    private function __construct(
        public readonly ExecutionFailureReason $reason,
        public readonly string $message,
    ) {}

    public static function of(ExecutionFailureReason $reason, string $message): self
    {
        return new self($reason, $message);
    }

    public static function unsupportedIntent(string $message): self
    {
        return new self(ExecutionFailureReason::UnsupportedIntent, $message);
    }

    public static function executionFailed(string $message): self
    {
        return new self(ExecutionFailureReason::ExecutionFailed, $message);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reason' => $this->reason->value,
            'message' => $this->message,
        ];
    }
}
