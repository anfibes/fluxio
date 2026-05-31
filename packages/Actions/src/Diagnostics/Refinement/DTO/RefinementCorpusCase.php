<?php

namespace Fluxio\Actions\Diagnostics\Refinement\DTO;

/**
 * One versioned refinement evaluation example. Built by RefinementCorpusLoader
 * from the corpus JSON.
 *
 * Refinements are proposal-context-dependent, so a case carries the
 * initial_command used to create the proposal, optional setup_refinements
 * applied (but NOT measured) to establish context, and the single measured
 * refinement_text. Expectations are partial: only the listed semantic types /
 * changed fields must be present; absent expectation keys are not checked.
 *
 * `expectedAmbiguity`, when present, is a validated associative array:
 *   - key:    string (required) — the ambiguity key to inspect
 *   - resolved: bool (required) — selected_candidate_id !== null after refinement
 *   - blocking: bool (optional) — the ambiguity's blocking flag
 *   - remaining_candidate_types:  list<string> (optional) — every active candidate type must be in this set
 *   - remaining_candidate_labels: list<string> (optional) — the active candidate labels (set-equal)
 */
final class RefinementCorpusCase
{
    /**
     * @param  list<string>  $setupRefinements
     * @param  list<string>  $expectedSemanticTypes
     * @param  list<string>  $expectedChangedFields
     * @param  list<string>  $expectedWarningsContain
     * @param  array<string, mixed>|null  $expectedAmbiguity
     * @param  list<string>  $notes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $description,
        public readonly string $initialCommand,
        public readonly string $refinementText,
        public readonly array $setupRefinements = [],
        public readonly array $expectedSemanticTypes = [],
        public readonly array $expectedChangedFields = [],
        public readonly bool $expectNoChanges = false,
        public readonly ?string $expectedStatus = null,
        public readonly array $expectedWarningsContain = [],
        public readonly ?array $expectedAmbiguity = null,
        public readonly array $notes = [],
    ) {}
}
