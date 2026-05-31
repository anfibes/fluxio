<?php

namespace Fluxio\Actions\Diagnostics\Refinement;

use App\Models\User;
use Fluxio\Actions\Diagnostics\Refinement\DTO\RefinementCaseResult;
use Fluxio\Actions\Diagnostics\Refinement\DTO\RefinementCorpusCase;
use Fluxio\Actions\Diagnostics\Refinement\DTO\RefinementEvaluationSummary;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Actions\Services\ActionInterpreterService;
use Fluxio\Actions\Services\ActionProposalPersistenceService;
use Fluxio\Actions\Services\ActionProposalRefinementService;

/**
 * Evaluates the refinement corpus against the current rule-based refinement
 * interpreter by replaying the real proposal pipeline for each case:
 *
 *   interpret(initial_command) -> persist -> [setup_refinements] -> refinement_text
 *
 * and comparing the resulting proposal's last_refinement / ambiguities / status /
 * warnings against the case expectations.
 *
 * Diagnostics-only. It uses the same authoritative services the runtime uses
 * (no fake proposal format), changes no runtime behavior, calls no LLM, and is
 * transaction-agnostic: the caller (the console command) is responsible for
 * wrapping the run in a rolled-back transaction so nothing is persisted. Tests
 * that use RefreshDatabase may call it directly.
 *
 * Comparison is intentionally simple and partial: only the listed semantic types
 * and changed fields must be present; absent expectation keys are not checked.
 */
class RefinementEvaluationService
{
    public function __construct(
        private readonly ActionInterpreterService $interpreter,
        private readonly ActionProposalPersistenceService $persistence,
        private readonly ActionProposalRefinementService $refinement,
    ) {}

    /**
     * @param  list<RefinementCorpusCase>  $cases
     */
    public function evaluate(array $cases, User $user): RefinementEvaluationSummary
    {
        $results = [];
        $passed = 0;

        foreach ($cases as $case) {
            $result = $this->evaluateCase($case, $user);
            $results[] = $result;
            $passed += $result->passed ? 1 : 0;
        }

        return new RefinementEvaluationSummary(
            total: count($cases),
            passedCount: $passed,
            failedCount: count($cases) - $passed,
            cases: $results,
        );
    }

    private function evaluateCase(RefinementCorpusCase $case, User $user): RefinementCaseResult
    {
        $proposalData = $this->interpreter->interpret($case->initialCommand);
        $proposal = $this->persistence->store($proposalData, $user);

        // Setup refinements establish proposal context (e.g. add a participant
        // before a remove/replace). They are applied but NOT measured.
        foreach ($case->setupRefinements as $setupText) {
            $proposal = $this->refinement->refine($proposal, $setupText);
        }

        // The single measured refinement.
        $proposal = $this->refinement->refine($proposal, $case->refinementText);

        $changes = $proposal->last_refinement['changes'] ?? [];
        $actualSemanticTypes = $this->collectSemanticTypes($changes);
        $actualChangedFields = $this->collectChangedFields($changes);
        $warnings = $proposal->warnings ?? [];

        $failures = $this->compare($case, $proposal, $changes, $actualSemanticTypes, $actualChangedFields, $warnings);

        return new RefinementCaseResult(
            id: $case->id,
            description: $case->description,
            passed: $failures === [],
            failures: $failures,
            actual: [
                'semantic_types' => $actualSemanticTypes,
                'changed_fields' => $actualChangedFields,
                'status' => $proposal->status,
                'warnings' => array_values($warnings),
                'ambiguity' => $case->expectedAmbiguity !== null
                    ? $this->ambiguitySnapshot($proposal, (string) $case->expectedAmbiguity['key'])
                    : null,
            ],
        );
    }

    /**
     * @param  array<int, mixed>  $changes
     * @param  list<string>  $actualSemanticTypes
     * @param  list<string>  $actualChangedFields
     * @param  list<string>  $warnings
     * @return list<string>
     */
    private function compare(
        RefinementCorpusCase $case,
        ActionProposal $proposal,
        array $changes,
        array $actualSemanticTypes,
        array $actualChangedFields,
        array $warnings,
    ): array {
        $failures = [];

        if ($case->expectNoChanges && $this->fieldChanges($changes) !== []) {
            $failures[] = sprintf(
                'expected no field changes, got [%s]',
                implode(', ', $actualChangedFields),
            );
        }

        foreach ($case->expectedSemanticTypes as $type) {
            if (! in_array($type, $actualSemanticTypes, true)) {
                $failures[] = sprintf('missing semantic_type [%s] (got [%s])', $type, implode(', ', $actualSemanticTypes));
            }
        }

        foreach ($case->expectedChangedFields as $field) {
            if (! in_array($field, $actualChangedFields, true)) {
                $failures[] = sprintf('missing changed field [%s] (got [%s])', $field, implode(', ', $actualChangedFields));
            }
        }

        if ($case->expectedStatus !== null && $proposal->status !== $case->expectedStatus) {
            $failures[] = sprintf('expected status [%s], got [%s]', $case->expectedStatus, $proposal->status);
        }

        foreach ($case->expectedWarningsContain as $needle) {
            if (! $this->anyWarningContains($warnings, $needle)) {
                $failures[] = sprintf('expected a warning containing [%s]', $needle);
            }
        }

        if ($case->expectedAmbiguity !== null) {
            $failures = array_merge($failures, $this->compareAmbiguity($case->expectedAmbiguity, $proposal));
        }

        return $failures;
    }

    /**
     * @param  array<string, mixed>  $expected
     * @return list<string>
     */
    private function compareAmbiguity(array $expected, ActionProposal $proposal): array
    {
        $failures = [];
        $key = (string) $expected['key'];
        $ambiguity = $this->findAmbiguity($proposal, $key);

        if ($ambiguity === null) {
            return ["expected ambiguity [{$key}] not present"];
        }

        $resolved = ($ambiguity['selected_candidate_id'] ?? null) !== null;
        if ($resolved !== (bool) $expected['resolved']) {
            $failures[] = sprintf(
                'ambiguity [%s] resolved expected %s, got %s',
                $key,
                $expected['resolved'] ? 'true' : 'false',
                $resolved ? 'true' : 'false',
            );
        }

        if (array_key_exists('blocking', $expected) && (bool) ($ambiguity['blocking'] ?? false) !== $expected['blocking']) {
            $failures[] = sprintf('ambiguity [%s] blocking expected %s', $key, $expected['blocking'] ? 'true' : 'false');
        }

        $candidates = $ambiguity['candidates'] ?? [];

        if (array_key_exists('remaining_candidate_types', $expected)) {
            $allowed = $expected['remaining_candidate_types'];
            foreach ($candidates as $candidate) {
                $type = $candidate['type'] ?? null;
                if (! in_array($type, $allowed, true)) {
                    $failures[] = sprintf('ambiguity [%s] has candidate type [%s] outside expected [%s]', $key, (string) $type, implode(', ', $allowed));
                    break;
                }
            }
        }

        if (array_key_exists('remaining_candidate_labels', $expected)) {
            $actualLabels = array_map(static fn (array $c): string => (string) ($c['label'] ?? ''), $candidates);
            sort($actualLabels);
            $expectedLabels = $expected['remaining_candidate_labels'];
            sort($expectedLabels);
            if ($actualLabels !== $expectedLabels) {
                $failures[] = sprintf(
                    'ambiguity [%s] remaining candidates expected [%s], got [%s]',
                    $key,
                    implode(', ', $expectedLabels),
                    implode(', ', $actualLabels),
                );
            }
        }

        return $failures;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findAmbiguity(ActionProposal $proposal, string $key): ?array
    {
        foreach ($proposal->ambiguities ?? [] as $ambiguity) {
            if (($ambiguity['key'] ?? null) === $key) {
                return $ambiguity;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ambiguitySnapshot(ActionProposal $proposal, string $key): ?array
    {
        $ambiguity = $this->findAmbiguity($proposal, $key);
        if ($ambiguity === null) {
            return null;
        }

        return [
            'key' => $key,
            'selected_candidate_id' => $ambiguity['selected_candidate_id'] ?? null,
            'blocking' => (bool) ($ambiguity['blocking'] ?? false),
            'candidate_labels' => array_values(array_map(
                static fn (array $c): string => (string) ($c['label'] ?? ''),
                $ambiguity['candidates'] ?? [],
            )),
            'candidate_types' => array_values(array_map(
                static fn (array $c): string => (string) ($c['type'] ?? ''),
                $ambiguity['candidates'] ?? [],
            )),
        ];
    }

    /**
     * Change entries that represent a field mutation (excludes the synthetic
     * status change so "no field changes" stays meaningful even when readiness
     * flips).
     *
     * @param  array<int, mixed>  $changes
     * @return array<int, mixed>
     */
    private function fieldChanges(array $changes): array
    {
        return array_values(array_filter(
            $changes,
            static fn ($c): bool => is_array($c) && ($c['field'] ?? null) !== 'status',
        ));
    }

    /**
     * @param  array<int, mixed>  $changes
     * @return list<string>
     */
    private function collectSemanticTypes(array $changes): array
    {
        $types = [];
        foreach ($changes as $change) {
            if (is_array($change) && isset($change['semantic_type']) && is_string($change['semantic_type'])) {
                $types[$change['semantic_type']] = true;
            }
        }

        return array_keys($types);
    }

    /**
     * @param  array<int, mixed>  $changes
     * @return list<string>
     */
    private function collectChangedFields(array $changes): array
    {
        $fields = [];
        foreach ($this->fieldChanges($changes) as $change) {
            if (is_array($change) && isset($change['field']) && is_string($change['field'])) {
                $fields[$change['field']] = true;
            }
        }

        return array_keys($fields);
    }

    /**
     * @param  list<string>  $warnings
     */
    private function anyWarningContains(array $warnings, string $needle): bool
    {
        foreach ($warnings as $warning) {
            if (is_string($warning) && str_contains($warning, $needle)) {
                return true;
            }
        }

        return false;
    }
}
