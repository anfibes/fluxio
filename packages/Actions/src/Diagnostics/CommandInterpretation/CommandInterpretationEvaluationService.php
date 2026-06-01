<?php

namespace Fluxio\Actions\Diagnostics\CommandInterpretation;

use Fluxio\Actions\Diagnostics\CommandInterpretation\DTO\CommandInterpretationCaseResult;
use Fluxio\Actions\Diagnostics\CommandInterpretation\DTO\CommandInterpretationCorpusCase;
use Fluxio\Actions\Diagnostics\CommandInterpretation\DTO\CommandInterpretationEvaluationSummary;
use Fluxio\Actions\DTO\ActionProposalData;
use Fluxio\Actions\DTO\MissingField;
use Fluxio\Actions\Services\ActionInterpreterService;

/**
 * Evaluates the command-interpretation corpus against the current deterministic
 * interpretation pipeline by replaying, per case:
 *
 *   ActionInterpreterService::interpret($text) -> ActionProposalData
 *
 * and comparing the proposal-level result (intent, status, resolved lead,
 * entities, missing keys, ambiguity) against the case expectations.
 *
 * Diagnostics-only. It uses the same authoritative interpreter the runtime uses,
 * changes no runtime behavior, and calls no LLM. The pipeline is DB-free
 * (interpretation produces an ActionProposalData DTO and the lead resolver is
 * in-memory), so this evaluator persists nothing and needs no transaction.
 *
 * Comparison is intentionally partial: only the keys a case asserts are checked.
 * The four fidelity dimensions (intent | status | entity | ambiguity) are tracked
 * per case so the summary can report per-dimension accuracy.
 *
 * The deterministic provider is the baseline. This is the corpus a future
 * (LLM-assisted) provider must be evaluated against before it can be trusted; it
 * confers no authority on any provider.
 */
class CommandInterpretationEvaluationService
{
    private const DIMENSIONS = ['intent', 'status', 'entity', 'ambiguity'];

    public function __construct(
        private readonly ActionInterpreterService $interpreter,
    ) {}

    /**
     * @param  list<CommandInterpretationCorpusCase>  $cases
     */
    public function evaluate(array $cases): CommandInterpretationEvaluationSummary
    {
        $results = [];
        $passed = 0;
        $metrics = array_fill_keys(self::DIMENSIONS, ['checked' => 0, 'matched' => 0]);

        foreach ($cases as $case) {
            $result = $this->evaluateCase($case);
            $results[] = $result;
            $passed += $result->passed ? 1 : 0;

            foreach ($result->dimensions as $dimension => $ok) {
                $metrics[$dimension]['checked']++;
                $metrics[$dimension]['matched'] += $ok ? 1 : 0;
            }
        }

        return new CommandInterpretationEvaluationSummary(
            total: count($cases),
            passedCount: $passed,
            failedCount: count($cases) - $passed,
            cases: $results,
            metrics: $metrics,
        );
    }

    private function evaluateCase(CommandInterpretationCorpusCase $case): CommandInterpretationCaseResult
    {
        $data = $this->interpreter->interpret($case->text);

        $failures = [];
        $dimensions = [];

        if ($case->expectedIntent !== null) {
            $ok = $data->intent === $case->expectedIntent;
            $dimensions['intent'] = $ok;
            if (! $ok) {
                $failures[] = sprintf('expected intent [%s], got [%s]', $case->expectedIntent, $data->intent);
            }
        }

        if ($case->expectedStatus !== null) {
            $ok = $data->status === $case->expectedStatus;
            $dimensions['status'] = $ok;
            if (! $ok) {
                $failures[] = sprintf('expected status [%s], got [%s]', $case->expectedStatus, $data->status);
            }
        }

        if ($this->checksEntity($case)) {
            $entityFailures = $this->compareEntities($case, $data);
            $dimensions['entity'] = $entityFailures === [];
            $failures = array_merge($failures, $entityFailures);
        }

        if ($case->expectNoLeadAmbiguity || $case->expectedAmbiguity !== null) {
            $ambiguityFailures = $this->compareAmbiguity($case, $data);
            $dimensions['ambiguity'] = $ambiguityFailures === [];
            $failures = array_merge($failures, $ambiguityFailures);
        }

        return new CommandInterpretationCaseResult(
            id: $case->id,
            description: $case->description,
            passed: $failures === [],
            failures: $failures,
            dimensions: $dimensions,
            actual: [
                'intent' => $data->intent,
                'status' => $data->status,
                'lead' => $data->entities['lead'] ?? null,
                'entities' => $data->entities,
                'missing' => $this->missingKeys($data),
                'ambiguities' => $this->ambiguitySnapshots($data),
            ],
        );
    }

    private function checksEntity(CommandInterpretationCorpusCase $case): bool
    {
        return $case->expectedLead !== null
            || $case->expectedEntities !== []
            || $case->expectedEntitiesPresent !== []
            || $case->expectedMissing !== [];
    }

    /**
     * @return list<string>
     */
    private function compareEntities(CommandInterpretationCorpusCase $case, ActionProposalData $data): array
    {
        $failures = [];

        if ($case->expectedLead !== null) {
            $actualLead = $data->entities['lead'] ?? null;
            if ($actualLead !== $case->expectedLead) {
                $failures[] = sprintf('expected lead [%s], got [%s]', $case->expectedLead, $actualLead ?? 'null');
            }
        }

        foreach ($case->expectedEntities as $key => $value) {
            $actual = $data->entities[$key] ?? null;
            if ((string) $actual !== (string) $value) {
                $failures[] = sprintf('entity [%s] expected [%s], got [%s]', $key, (string) $value, $actual === null ? 'null' : (string) $actual);
            }
        }

        foreach ($case->expectedEntitiesPresent as $key) {
            $actual = $data->entities[$key] ?? null;
            if ($actual === null || $actual === '') {
                $failures[] = sprintf('entity [%s] expected present, but it is absent', $key);
            }
        }

        if ($case->expectedMissing !== []) {
            $actualMissing = $this->missingKeys($data);
            foreach ($case->expectedMissing as $key) {
                if (! in_array($key, $actualMissing, true)) {
                    $failures[] = sprintf('expected missing key [%s] (got [%s])', $key, implode(', ', $actualMissing) ?: '—');
                }
            }
        }

        return $failures;
    }

    /**
     * @return list<string>
     */
    private function compareAmbiguity(CommandInterpretationCorpusCase $case, ActionProposalData $data): array
    {
        $failures = [];

        if ($case->expectNoLeadAmbiguity) {
            $blocking = $this->findAmbiguity($data, 'lead');
            if ($blocking !== null && ($blocking['blocking'] ?? false)) {
                $failures[] = sprintf(
                    'expected no blocking lead ambiguity, got candidates [%s]',
                    implode(', ', $this->candidateLabels($blocking)),
                );
            }
        }

        $expected = $case->expectedAmbiguity;
        if ($expected === null) {
            return $failures;
        }

        $key = (string) $expected['key'];
        $ambiguity = $this->findAmbiguity($data, $key);

        if ($ambiguity === null) {
            $failures[] = "expected ambiguity [{$key}] not present";

            return $failures;
        }

        if (array_key_exists('blocking', $expected) && (bool) ($ambiguity['blocking'] ?? false) !== $expected['blocking']) {
            $failures[] = sprintf('ambiguity [%s] blocking expected %s', $key, $expected['blocking'] ? 'true' : 'false');
        }

        if (array_key_exists('query', $expected) && ($ambiguity['query'] ?? null) !== $expected['query']) {
            $failures[] = sprintf('ambiguity [%s] query expected [%s], got [%s]', $key, (string) $expected['query'], (string) ($ambiguity['query'] ?? 'null'));
        }

        if (array_key_exists('candidate_labels', $expected)) {
            $actualLabels = $this->candidateLabels($ambiguity);
            sort($actualLabels);
            $expectedLabels = $expected['candidate_labels'];
            sort($expectedLabels);
            if ($actualLabels !== $expectedLabels) {
                $failures[] = sprintf(
                    'ambiguity [%s] candidates expected [%s], got [%s]',
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
    private function findAmbiguity(ActionProposalData $data, string $key): ?array
    {
        foreach ($data->ambiguities as $ambiguity) {
            if (is_array($ambiguity) && ($ambiguity['key'] ?? null) === $key) {
                return $ambiguity;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $ambiguity
     * @return list<string>
     */
    private function candidateLabels(array $ambiguity): array
    {
        return array_values(array_map(
            static fn ($c): string => (string) ($c['label'] ?? ''),
            $ambiguity['candidates'] ?? [],
        ));
    }

    /**
     * @return list<string>
     */
    private function missingKeys(ActionProposalData $data): array
    {
        return array_values(array_map(
            static fn (MissingField $f): string => $f->key,
            $data->missing,
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ambiguitySnapshots(ActionProposalData $data): array
    {
        $snapshots = [];
        foreach ($data->ambiguities as $ambiguity) {
            if (! is_array($ambiguity)) {
                continue;
            }
            $snapshots[] = [
                'key' => $ambiguity['key'] ?? null,
                'blocking' => (bool) ($ambiguity['blocking'] ?? false),
                'query' => $ambiguity['query'] ?? null,
                'candidate_labels' => $this->candidateLabels($ambiguity),
            ];
        }

        return $snapshots;
    }
}
