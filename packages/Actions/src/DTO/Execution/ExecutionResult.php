<?php

namespace Fluxio\Actions\DTO\Execution;

/**
 * The typed result of a successful action execution (Execution Runtime v1).
 *
 * Execution is the third deterministic runtime authority — the only one allowed
 * to produce external side effects, and only from a confirmed proposal, once,
 * atomically. This value object is what every executor returns instead of an ad
 * hoc array, giving the runtime one closed, consistent execution-result contract
 * across all intents.
 *
 * It is inert, response-bound display data (like last_refinement): persisted into
 * `execution_result`, surfaced to the frontend, and never read back by the
 * runtime. It carries NO provider/provenance/confidence surface — it names what
 * happened, never who produced the interpretation.
 *
 *  - `summary` : a short human one-liner describing the effect.
 *  - `details` : flat, presentation-ready key → scalar pairs (e.g. the created
 *                resource reference, or the scheduled lead/date/time). No nested
 *                structures: the frontend renders them as a label/value list.
 */
final class ExecutionResult
{
    /**
     * @param  array<string, scalar|null>  $details
     */
    private function __construct(
        public readonly string $summary,
        public readonly array $details = [],
    ) {}

    /**
     * @param  array<string, scalar|null>  $details
     */
    public static function make(string $summary, array $details = []): self
    {
        return new self($summary, $details);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'details' => $this->details,
        ];
    }
}
