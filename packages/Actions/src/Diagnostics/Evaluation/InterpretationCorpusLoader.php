<?php

namespace Fluxio\Actions\Diagnostics\Evaluation;

use Fluxio\Actions\Diagnostics\Evaluation\DTO\InterpretationCorpusCase;
use Fluxio\Actions\Diagnostics\Evaluation\Exceptions\InvalidInterpretationCorpusException;

/**
 * Loads and validates the versioned interpretation evaluation corpus.
 *
 * Diagnostics-only. Validation is intentionally simple and explicit: an invalid
 * corpus aborts loading by throwing InvalidInterpretationCorpusException. It does
 * not normalise, resolve, or interpret anything — it only turns valid JSON into
 * InterpretationCorpusCase DTOs.
 */
class InterpretationCorpusLoader
{
    /**
     * Default shipped corpus: packages/Actions/evaluation/interpretation-corpus.json.
     * (src/Diagnostics/Evaluation → src/Diagnostics → src → Actions → evaluation)
     */
    public static function defaultCorpusPath(): string
    {
        return __DIR__.'/../../../evaluation/interpretation-corpus.json';
    }

    /**
     * @return list<InterpretationCorpusCase>
     *
     * @throws InvalidInterpretationCorpusException
     */
    public function load(string $path): array
    {
        if (! is_file($path)) {
            throw new InvalidInterpretationCorpusException("Corpus file not found at [{$path}].");
        }

        $raw = (string) file_get_contents($path);
        $decoded = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidInterpretationCorpusException(
                'Corpus file is not valid JSON: '.json_last_error_msg().'.',
            );
        }

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new InvalidInterpretationCorpusException('Corpus root must be a JSON array of cases.');
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
    private function parseCase($case, int $index): InterpretationCorpusCase
    {
        if (! is_array($case) || array_is_list($case)) {
            throw new InvalidInterpretationCorpusException("Case at index [{$index}] must be a JSON object.");
        }

        $id = $case['id'] ?? null;
        if (! is_string($id) || trim($id) === '') {
            throw new InvalidInterpretationCorpusException("Case at index [{$index}] is missing a non-empty [id].");
        }

        $text = $case['text'] ?? null;
        if (! is_string($text) || trim($text) === '') {
            throw new InvalidInterpretationCorpusException("Case [{$id}] is missing a non-empty [text].");
        }

        $expected = $case['expected'] ?? null;
        if (! is_array($expected) || array_is_list($expected)) {
            throw new InvalidInterpretationCorpusException("Case [{$id}] is missing an [expected] object.");
        }

        $expectedIntent = $expected['intent'] ?? null;
        if (! is_string($expectedIntent) || trim($expectedIntent) === '') {
            throw new InvalidInterpretationCorpusException("Case [{$id}] is missing a non-empty [expected.intent].");
        }

        $expectedEntities = [];
        if (array_key_exists('entities', $expected)) {
            $entities = $expected['entities'];
            if (! is_array($entities) || array_is_list($entities)) {
                throw new InvalidInterpretationCorpusException("Case [{$id}] has a malformed [expected.entities]; it must be an object.");
            }
            $expectedEntities = $entities;
        }

        $notes = [];
        if (array_key_exists('notes', $case)) {
            $rawNotes = $case['notes'];
            if (! is_array($rawNotes) || ! array_is_list($rawNotes)) {
                throw new InvalidInterpretationCorpusException("Case [{$id}] has malformed [notes]; it must be a list of strings.");
            }
            $notes = array_values(array_map(static fn ($n): string => (string) $n, $rawNotes));
        }

        $locale = $case['locale'] ?? 'en';
        if (! is_string($locale) || trim($locale) === '') {
            $locale = 'en';
        }

        return new InterpretationCorpusCase(
            id: $id,
            text: $text,
            locale: $locale,
            expectedIntent: $expectedIntent,
            expectedEntities: $expectedEntities,
            notes: $notes,
        );
    }
}
