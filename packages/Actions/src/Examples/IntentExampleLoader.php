<?php

namespace Fluxio\Actions\Examples;

use Fluxio\Actions\Examples\Exceptions\InvalidIntentExampleException;
use Fluxio\Actions\Interpretation\InterpretationGrammar;
use Fluxio\Actions\Registry\IntentRegistry;

/**
 * Loads and validates a versioned, per-locale intent-examples file.
 *
 * Fail-closed and read-only: validation is explicit and any malformation throws
 * InvalidIntentExampleException, mirroring the diagnostics corpus loaders. It does
 * NOT interpret, resolve, normalise, or build anything — it only turns valid JSON
 * into IntentExample DTOs, validated against the authoritative IntentRegistry /
 * IntentDefinition requirement keys (plus the universal parser keys).
 *
 * Authority boundary: IntentRegistry remains the intent + requirement-key authority
 * (consulted here, never duplicated). This loader decides nothing about readiness,
 * lifecycle, ambiguity, entity resolution, or execution.
 */
class IntentExampleLoader
{
    /**
     * Recognised top-level record fields. Any other key fails closed — this is how
     * forbidden runtime/authority fields (status, ready, confirmed, executed,
     * execution_result, execution_failure, proposal_id, …) are rejected at the top
     * level without needing an exhaustive blocklist.
     *
     * @var list<string>
     */
    private const ALLOWED_FIELDS = ['id', 'locale', 'intent', 'text', 'template', 'slots', 'source', 'tags', 'notes'];

    /**
     * Explicitly forbidden slot/entity keys (defence in depth — they are already
     * impossible because slot keys must be requirement/parser keys, but naming them
     * yields a precise error and guards against future requirement-key drift).
     *
     * @var list<string>
     */
    private const FORBIDDEN_ENTITY_KEYS = [
        'id', 'lead_id', 'selected_candidate_id', 'candidate_id', 'proposal_id',
        'status', 'ready', 'confirmed', 'executed', 'execution_result', 'execution_failure',
    ];

    /**
     * Allowed provenance values for a record's `source`.
     *
     * @var list<string>
     */
    private const ALLOWED_SOURCES = ['seed', 'manual', 'generated', 'confirmed_execution'];

    public function __construct(private readonly IntentRegistry $registry) {}

    /**
     * Load and validate one locale file.
     *
     * @return list<IntentExample>
     *
     * @throws InvalidIntentExampleException
     */
    public function load(string $path, string $expectedLocale): array
    {
        if (! is_file($path)) {
            throw new InvalidIntentExampleException("Intent-examples file not found at [{$path}].");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidIntentExampleException(
                "Intent-examples file [{$path}] is not valid JSON: ".json_last_error_msg().'.',
            );
        }

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new InvalidIntentExampleException("Intent-examples file [{$path}] root must be a JSON array of records.");
        }

        $examples = [];
        $seenIds = [];

        foreach ($decoded as $index => $record) {
            $example = $this->parseRecord($record, $index, $expectedLocale, $path);

            if (isset($seenIds[$example->id])) {
                throw new InvalidIntentExampleException("Duplicate intent-example id [{$example->id}] in [{$path}].");
            }
            $seenIds[$example->id] = true;

            $examples[] = $example;
        }

        return $examples;
    }

    /**
     * @param  mixed  $record
     *
     * @throws InvalidIntentExampleException
     */
    private function parseRecord($record, int $index, string $expectedLocale, string $path): IntentExample
    {
        if (! is_array($record) || array_is_list($record)) {
            throw new InvalidIntentExampleException("Record at index [{$index}] in [{$path}] must be a JSON object.");
        }

        foreach (array_keys($record) as $key) {
            if (! in_array($key, self::ALLOWED_FIELDS, true)) {
                throw new InvalidIntentExampleException("Record at index [{$index}] in [{$path}] has an unknown/forbidden field [{$key}].");
            }
        }

        $id = $record['id'] ?? null;
        if (! is_string($id) || trim($id) === '') {
            throw new InvalidIntentExampleException("Record at index [{$index}] in [{$path}] is missing a non-empty [id].");
        }

        $locale = $record['locale'] ?? null;
        if (! is_string($locale) || trim($locale) === '') {
            throw new InvalidIntentExampleException("Record [{$id}] is missing a non-empty [locale].");
        }
        if ($locale !== $expectedLocale) {
            throw new InvalidIntentExampleException("Record [{$id}] has locale [{$locale}] but file expects [{$expectedLocale}].");
        }

        $intent = $record['intent'] ?? null;
        if (! is_string($intent) || trim($intent) === '') {
            throw new InvalidIntentExampleException("Record [{$id}] is missing a non-empty [intent].");
        }
        $definition = $this->registry->find($intent);
        if ($definition === null) {
            throw new InvalidIntentExampleException("Record [{$id}] references unknown intent [{$intent}].");
        }

        $text = $this->parseOptionalString($record, 'text', $id);
        $template = $this->parseOptionalString($record, 'template', $id);

        if ($text === null && $template === null) {
            throw new InvalidIntentExampleException("Record [{$id}] must carry a non-empty [text] and/or [template].");
        }

        $allowedKeys = array_merge($definition->requirementKeys(), InterpretationGrammar::UNIVERSAL_PARSER_KEYS);

        $slots = $this->parseSlots($record, $id, $allowedKeys);

        // Every {placeholder} in the template must be backed by a declared slot.
        if ($template !== null) {
            $this->assertTemplatePlaceholdersCovered($template, $slots, $id);
        }

        $source = $this->parseSource($record, $id);
        $tags = $this->parseStringList($record, 'tags', $id);
        $notes = $this->parseStringList($record, 'notes', $id);

        return new IntentExample(
            id: $id,
            locale: $locale,
            intent: $intent,
            text: $text,
            template: $template,
            slots: $slots,
            source: $source,
            tags: $tags,
            notes: $notes,
        );
    }

    private function parseOptionalString(array $record, string $key, string $id): ?string
    {
        if (! array_key_exists($key, $record)) {
            return null;
        }

        $value = $record[$key];
        if (! is_string($value)) {
            throw new InvalidIntentExampleException("Record [{$id}] has a malformed [{$key}]; it must be a string.");
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @param  list<string>  $allowedKeys
     * @return array<string, array{placeholder: string, entity_key: string}>
     */
    private function parseSlots(array $record, string $id, array $allowedKeys): array
    {
        if (! array_key_exists('slots', $record)) {
            return [];
        }

        $rawSlots = $record['slots'];
        if (! is_array($rawSlots) || array_is_list($rawSlots)) {
            throw new InvalidIntentExampleException("Record [{$id}] has a malformed [slots]; it must be an object.");
        }

        $slots = [];

        foreach ($rawSlots as $slotName => $slot) {
            if (! is_string($slotName) || ! is_array($slot)) {
                throw new InvalidIntentExampleException("Record [{$id}] has a malformed slot entry [{$slotName}].");
            }

            $placeholder = $slot['placeholder'] ?? null;
            $entityKey = $slot['entity_key'] ?? null;

            if (! is_string($placeholder) || trim($placeholder) === '') {
                throw new InvalidIntentExampleException("Slot [{$slotName}] in record [{$id}] is missing a non-empty [placeholder].");
            }
            if (! is_string($entityKey) || trim($entityKey) === '') {
                throw new InvalidIntentExampleException("Slot [{$slotName}] in record [{$id}] is missing a non-empty [entity_key].");
            }

            if (in_array($entityKey, self::FORBIDDEN_ENTITY_KEYS, true)) {
                throw new InvalidIntentExampleException("Slot [{$slotName}] in record [{$id}] uses a forbidden entity_key [{$entityKey}].");
            }

            if (! in_array($entityKey, $allowedKeys, true)) {
                throw new InvalidIntentExampleException(
                    "Slot [{$slotName}] in record [{$id}] maps to entity_key [{$entityKey}] which is not a requirement or parser key for intent [{$record['intent']}]. Allowed: ".implode(', ', $allowedKeys).'.',
                );
            }

            $slots[$slotName] = ['placeholder' => $placeholder, 'entity_key' => $entityKey];
        }

        return $slots;
    }

    /**
     * @param  array<string, array{placeholder: string, entity_key: string}>  $slots
     */
    private function assertTemplatePlaceholdersCovered(string $template, array $slots, string $id): void
    {
        preg_match_all('/\{(\w+)\}/', $template, $matches);

        $declaredPlaceholders = array_map(static fn (array $s): string => $s['placeholder'], array_values($slots));

        foreach ($matches[0] as $placeholder) {
            if (! in_array($placeholder, $declaredPlaceholders, true)) {
                throw new InvalidIntentExampleException("Template in record [{$id}] uses placeholder [{$placeholder}] with no matching slot.");
            }
        }
    }

    private function parseSource(array $record, string $id): string
    {
        if (! array_key_exists('source', $record)) {
            return 'seed';
        }

        $source = $record['source'];
        if (! is_string($source) || ! in_array($source, self::ALLOWED_SOURCES, true)) {
            throw new InvalidIntentExampleException("Record [{$id}] has an invalid [source]; allowed: ".implode(', ', self::ALLOWED_SOURCES).'.');
        }

        return $source;
    }

    /**
     * @return list<string>
     */
    private function parseStringList(array $record, string $key, string $id): array
    {
        if (! array_key_exists($key, $record)) {
            return [];
        }

        $raw = $record[$key];
        if (! is_array($raw) || ! array_is_list($raw)) {
            throw new InvalidIntentExampleException("Record [{$id}] has a malformed [{$key}]; it must be a list of strings.");
        }

        return array_values(array_map(static fn ($v): string => (string) $v, $raw));
    }
}
