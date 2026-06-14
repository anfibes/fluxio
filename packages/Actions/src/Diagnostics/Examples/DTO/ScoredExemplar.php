<?php

namespace Fluxio\Actions\Diagnostics\Examples\DTO;

use Fluxio\Actions\Examples\IntentExample;

/**
 * Diagnostics-only, transparent scoring of one IntentExample candidate against a target
 * evaluation case (Slice A3.1 — Dynamic Exemplar Selection).
 *
 * The score is a plain, explainable sum of three signals — intent match, slot overlap, and a
 * small template-preference bonus — computed from already-known case metadata (intent + expected
 * entity keys), never from inferring intent out of free text. Every component is captured so the
 * ranking is fully auditable and testable; nothing here is authoritative or persisted.
 */
final class ScoredExemplar
{
    /**
     * @param  list<string>  $matchedSlots  The candidate slot entity-keys that overlapped the target.
     */
    public function __construct(
        public readonly IntentExample $example,
        public readonly int $score,
        public readonly bool $intentMatch,
        public readonly int $slotOverlap,
        public readonly bool $isTemplate,
        public readonly array $matchedSlots = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->example->id,
            'intent' => $this->example->intent,
            'score' => $this->score,
            'intent_match' => $this->intentMatch,
            'slot_overlap' => $this->slotOverlap,
            'matched_slots' => $this->matchedSlots,
            'is_template' => $this->isTemplate,
        ];
    }
}
