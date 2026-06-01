<?php

namespace Fluxio\Actions\Diagnostics\CommandInterpretation;

use Fluxio\Actions\Diagnostics\CommandInterpretation\DTO\CommandInterpretationCorpusCase;
use Fluxio\Actions\Diagnostics\CommandInterpretation\Exceptions\InvalidCommandInterpretationCorpusException;

/**
 * Loads and validates the versioned command-interpretation evaluation corpus.
 *
 * Diagnostics-only. Validation is intentionally simple and explicit: an invalid
 * corpus aborts loading by throwing InvalidCommandInterpretationCorpusException. It
 * does not interpret or persist anything — it only turns valid JSON into
 * CommandInterpretationCorpusCase DTOs. Mirrors RefinementCorpusLoader.
 */
class CommandInterpretationCorpusLoader
{
    /**
     * Default shipped corpus: packages/Actions/evaluation/command-interpretation-corpus.json.
     * (src/Diagnostics/CommandInterpretation → src/Diagnostics → src → Actions → evaluation)
     */
    public static function defaultCorpusPath(): string
    {
        return __DIR__.'/../../../evaluation/command-interpretation-corpus.json';
    }

    /**
     * @return list<CommandInterpretationCorpusCase>
     *
     * @throws InvalidCommandInterpretationCorpusException
     */
    public function load(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidCommandInterpretationCorpusException("Corpus file not found at [{$path}].");
        }

        $raw = (string) file_get_contents($path);
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidCommandInterpretationCorpusException(
                'Corpus file is not valid JSON: '.json_last_error_msg().'.',
            );
        }

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new InvalidCommandInterpretationCorpusException('Corpus root must be a JSON array of cases.');
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
    private function parseCase($case, int $index): CommandInterpretationCorpusCase
    {
        if (! is_array($case) || array_is_list($case)) {
            throw new InvalidCommandInterpretationCorpusException("Case at index [{$index}] must be a JSON object.");
        }

        $id = $case['id'] ?? null;
        if (! is_string($id) || trim($id) === '') {
            throw new InvalidCommandInterpretationCorpusException("Case at index [{$index}] is missing a non-empty [id].");
        }

        $text = $case['text'] ?? null;
        if (! is_string($text) || trim($text) === '') {
            throw new InvalidCommandInterpretationCorpusException("Case [{$id}] is missing a non-empty [text].");
        }

        $expected = $case['expected'] ?? null;
        if (! is_array($expected) || array_is_list($expected)) {
            throw new InvalidCommandInterpretationCorpusException("Case [{$id}] is missing an [expected] object.");
        }

        $description = is_string($case['description'] ?? null) ? $case['description'] : '';
        $notes = $this->stringList($case['notes'] ?? [], $id, 'notes');

        $intent = $this->nullableString($expected['intent'] ?? null, $id, 'expected.intent');
        $status = $this->nullableString($expected['status'] ?? null, $id, 'expected.status');
        $lead = $this->nullableString($expected['lead'] ?? null, $id, 'expected.lead');

        $entities = $expected['entities'] ?? [];
        if ($entities !== [] && (! is_array($entities) || array_is_list($entities))) {
            throw new InvalidCommandInterpretationCorpusException("Case [{$id}] has a malformed [expected.entities]; it must be an object.");
        }

        $entitiesPresent = $this->stringList($expected['entities_present'] ?? [], $id, 'expected.entities_present');
        $missing = $this->stringList($expected['missing'] ?? [], $id, 'expected.missing');
        $noLeadAmbiguity = (bool) ($expected['no_lead_ambiguity'] ?? false);

        $ambiguity = null;
        if (array_key_exists('ambiguity', $expected)) {
            $ambiguity = $this->parseAmbiguity($expected['ambiguity'], $id);
        }

        return new CommandInterpretationCorpusCase(
            id: $id,
            description: $description,
            text: $text,
            expectedIntent: $intent,
            expectedStatus: $status,
            expectedLead: $lead,
            expectedEntities: is_array($entities) ? $entities : [],
            expectedEntitiesPresent: $entitiesPresent,
            expectedMissing: $missing,
            expectNoLeadAmbiguity: $noLeadAmbiguity,
            expectedAmbiguity: $ambiguity,
            notes: $notes,
        );
    }

    /**
     * @param  mixed  $value
     */
    private function nullableString($value, string $id, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || trim($value) === '') {
            throw new InvalidCommandInterpretationCorpusException("Case [{$id}] has a malformed [{$field}]; it must be a non-empty string.");
        }

        return $value;
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
            throw new InvalidCommandInterpretationCorpusException("Case [{$id}] has a malformed [{$field}]; it must be a list of strings.");
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
            throw new InvalidCommandInterpretationCorpusException("Case [{$id}] has a malformed [expected.ambiguity]; it must be an object.");
        }

        $key = $ambiguity['key'] ?? null;
        if (! is_string($key) || trim($key) === '') {
            throw new InvalidCommandInterpretationCorpusException("Case [{$id}] [expected.ambiguity] is missing a non-empty [key].");
        }

        $parsed = ['key' => $key];

        if (array_key_exists('blocking', $ambiguity)) {
            if (! is_bool($ambiguity['blocking'])) {
                throw new InvalidCommandInterpretationCorpusException("Case [{$id}] [expected.ambiguity.blocking] must be a boolean.");
            }
            $parsed['blocking'] = $ambiguity['blocking'];
        }

        if (array_key_exists('query', $ambiguity)) {
            $parsed['query'] = $this->nullableString($ambiguity['query'], $id, 'expected.ambiguity.query');
        }

        if (array_key_exists('candidate_labels', $ambiguity)) {
            $parsed['candidate_labels'] = $this->stringList(
                $ambiguity['candidate_labels'],
                $id,
                'expected.ambiguity.candidate_labels',
            );
        }

        return $parsed;
    }
}
