<?php

namespace Fluxio\Actions\Diagnostics\Refinement;

use Fluxio\Actions\Diagnostics\Refinement\DTO\RefinementCorpusCase;
use Fluxio\Actions\Diagnostics\Refinement\Exceptions\InvalidRefinementCorpusException;

/**
 * Loads and validates the versioned refinement evaluation corpus.
 *
 * Diagnostics-only. Validation is intentionally simple and explicit: an invalid
 * corpus aborts loading by throwing InvalidRefinementCorpusException. It does not
 * interpret, persist, or refine anything — it only turns valid JSON into
 * RefinementCorpusCase DTOs. Mirrors InterpretationCorpusLoader.
 */
class RefinementCorpusLoader
{
    /**
     * Default shipped corpus: packages/Actions/evaluation/refinement-corpus.json.
     * (src/Diagnostics/Refinement → src/Diagnostics → src → Actions → evaluation)
     */
    public static function defaultCorpusPath(): string
    {
        return __DIR__.'/../../../evaluation/refinement-corpus.json';
    }

    /**
     * @return list<RefinementCorpusCase>
     *
     * @throws InvalidRefinementCorpusException
     */
    public function load(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidRefinementCorpusException("Corpus file not found at [{$path}].");
        }

        $raw = (string) file_get_contents($path);
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidRefinementCorpusException(
                'Corpus file is not valid JSON: '.json_last_error_msg().'.',
            );
        }

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new InvalidRefinementCorpusException('Corpus root must be a JSON array of cases.');
        }

        $cases = [];
        foreach ($decoded as $index => $case) {
            $cases[] = $this->parseCase($case, $index);
        }

        return $cases;
    }

    /**
     * @param  mixed  $case
     */
    private function parseCase($case, int $index): RefinementCorpusCase
    {
        if (! is_array($case) || array_is_list($case)) {
            throw new InvalidRefinementCorpusException("Case at index [{$index}] must be a JSON object.");
        }

        $id = $case['id'] ?? null;
        if (! is_string($id) || trim($id) === '') {
            throw new InvalidRefinementCorpusException("Case at index [{$index}] is missing a non-empty [id].");
        }

        $initialCommand = $case['initial_command'] ?? null;
        if (! is_string($initialCommand) || trim($initialCommand) === '') {
            throw new InvalidRefinementCorpusException("Case [{$id}] is missing a non-empty [initial_command].");
        }

        $refinementText = $case['refinement_text'] ?? null;
        if (! is_string($refinementText) || trim($refinementText) === '') {
            throw new InvalidRefinementCorpusException("Case [{$id}] is missing a non-empty [refinement_text].");
        }

        $expected = $case['expected'] ?? null;
        if (! is_array($expected) || array_is_list($expected)) {
            throw new InvalidRefinementCorpusException("Case [{$id}] is missing an [expected] object.");
        }

        $description = is_string($case['description'] ?? null) ? $case['description'] : '';
        $setupRefinements = $this->stringList($case['setup_refinements'] ?? [], $id, 'setup_refinements');
        $semanticTypes = $this->stringList($expected['semantic_types'] ?? [], $id, 'expected.semantic_types');
        $changedFields = $this->stringList($expected['changed_fields'] ?? [], $id, 'expected.changed_fields');
        $warningsContain = $this->stringList($expected['warnings_contain'] ?? [], $id, 'expected.warnings_contain');
        $notes = $this->stringList($case['notes'] ?? [], $id, 'notes');

        $expectNoChanges = (bool) ($expected['expect_no_changes'] ?? false);

        $status = $expected['status'] ?? null;
        if ($status !== null && (! is_string($status) || trim($status) === '')) {
            throw new InvalidRefinementCorpusException("Case [{$id}] has a malformed [expected.status]; it must be a non-empty string.");
        }

        $ambiguity = null;
        if (array_key_exists('ambiguity', $expected)) {
            $ambiguity = $this->parseAmbiguity($expected['ambiguity'], $id);
        }

        return new RefinementCorpusCase(
            id: $id,
            description: $description,
            initialCommand: $initialCommand,
            refinementText: $refinementText,
            setupRefinements: $setupRefinements,
            expectedSemanticTypes: $semanticTypes,
            expectedChangedFields: $changedFields,
            expectNoChanges: $expectNoChanges,
            expectedStatus: $status,
            expectedWarningsContain: $warningsContain,
            expectedAmbiguity: $ambiguity,
            notes: $notes,
        );
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private function stringList($value, string $id, string $field): array
    {
        if ($value === []) {
            return [];
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidRefinementCorpusException("Case [{$id}] has a malformed [{$field}]; it must be a list of strings.");
        }

        return array_values(array_map(static fn ($v): string => (string) $v, $value));
    }

    /**
     * @param  mixed  $ambiguity
     * @return array<string, mixed>
     */
    private function parseAmbiguity($ambiguity, string $id): array
    {
        if (! is_array($ambiguity) || array_is_list($ambiguity)) {
            throw new InvalidRefinementCorpusException("Case [{$id}] has a malformed [expected.ambiguity]; it must be an object.");
        }

        $key = $ambiguity['key'] ?? null;
        if (! is_string($key) || trim($key) === '') {
            throw new InvalidRefinementCorpusException("Case [{$id}] [expected.ambiguity] is missing a non-empty [key].");
        }

        if (! array_key_exists('resolved', $ambiguity) || ! is_bool($ambiguity['resolved'])) {
            throw new InvalidRefinementCorpusException("Case [{$id}] [expected.ambiguity] is missing a boolean [resolved].");
        }

        $parsed = [
            'key' => $key,
            'resolved' => $ambiguity['resolved'],
        ];

        if (array_key_exists('blocking', $ambiguity)) {
            if (! is_bool($ambiguity['blocking'])) {
                throw new InvalidRefinementCorpusException("Case [{$id}] [expected.ambiguity.blocking] must be a boolean.");
            }
            $parsed['blocking'] = $ambiguity['blocking'];
        }

        if (array_key_exists('remaining_candidate_types', $ambiguity)) {
            $parsed['remaining_candidate_types'] = $this->stringList(
                $ambiguity['remaining_candidate_types'],
                $id,
                'expected.ambiguity.remaining_candidate_types',
            );
        }

        if (array_key_exists('remaining_candidate_labels', $ambiguity)) {
            $parsed['remaining_candidate_labels'] = $this->stringList(
                $ambiguity['remaining_candidate_labels'],
                $id,
                'expected.ambiguity.remaining_candidate_labels',
            );
        }

        return $parsed;
    }
}
