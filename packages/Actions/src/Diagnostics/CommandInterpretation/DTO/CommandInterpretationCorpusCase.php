<?php

namespace Fluxio\Actions\Diagnostics\CommandInterpretation\DTO;

/**
 * One versioned command-interpretation evaluation example. Built by
 * CommandInterpretationCorpusLoader from the corpus JSON.
 *
 * Unlike the provider-level interpretation corpus (which asserts NormalizedCommand
 * intent/entities only), this measures PROPOSAL-LEVEL interpretation fidelity: the
 * case replays `ActionInterpreterService::interpret($text)` (which runs entity
 * resolution and proposal building) and compares the resulting ActionProposalData.
 *
 * Expectations are partial — only the listed keys are checked; absent keys are not.
 *
 * `expectedAmbiguity`, when present, is a validated associative array:
 *   - key:              string (required) — the ambiguity key to inspect
 *   - blocking:         bool   (optional) — the ambiguity's blocking flag
 *   - query:            string (optional) — the resolver query that produced it
 *   - candidate_labels: list<string> (optional) — the candidate labels (set-equal)
 */
final class CommandInterpretationCorpusCase
{
    /**
     * @param  array<string, mixed>  $expectedEntities  exact key→value subset to assert in entities
     * @param  list<string>  $expectedEntitiesPresent  entity keys that must be present and non-null
     * @param  list<string>  $expectedMissing  required-missing keys that must be present
     * @param  array<string, mixed>|null  $expectedAmbiguity
     * @param  list<string>  $notes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $description,
        public readonly string $text,
        public readonly ?string $expectedIntent = null,
        public readonly ?string $expectedStatus = null,
        public readonly ?string $expectedLead = null,
        public readonly array $expectedEntities = [],
        public readonly array $expectedEntitiesPresent = [],
        public readonly array $expectedMissing = [],
        public readonly bool $expectNoLeadAmbiguity = false,
        public readonly ?array $expectedAmbiguity = null,
        public readonly array $notes = [],
    ) {}
}
