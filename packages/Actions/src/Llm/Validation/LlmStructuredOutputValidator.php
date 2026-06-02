<?php

namespace Fluxio\Actions\Llm\Validation;

use Fluxio\Actions\Interpretation\InterpretationGrammar;
use Fluxio\Actions\Llm\Exceptions\InvalidLlmStructuredOutputException;

/**
 * Validates the parsed JSON produced by a future LLM-backed interpretation
 * provider *before* it is converted to NormalizedCommand.
 *
 * Boundary:
 *  - This validator enforces the LLM JSON contract (shape, safety, forbidden
 *    keys, intent-aware entity-key compatibility).
 *  - It does NOT replace NormalizedCommandValidator. After the future
 *    LlmInterpretationProvider builds a NormalizedCommand from validated
 *    JSON, NormalizedCommandValidator still runs at the proposal-pipeline
 *    boundary.
 *  - It does NOT build proposals, resolve entities, decide readiness,
 *    execute, or call any other Actions service.
 *
 * Rules (strict mode):
 *  - root must be an associative array
 *  - only {intent, confidence, entities, notes} are allowed at the root
 *  - intent: non-empty string; registered (per InterpretationGrammar) or "unknown"
 *  - confidence: numeric, within [0.0, 1.0]
 *  - entities: associative array, no forbidden keys, no *_id keys
 *  - entity values: non-empty scalar (string/int/float/bool) or array of
 *    non-empty scalars; null and empty strings are rejected; nested objects
 *    are rejected
 *  - "unknown" intent requires empty entities
 *  - non-"unknown" intent requires entity keys allowed for the intent by the
 *    runtime-owned InterpretationGrammar descriptor (Phase 9F.1B) — the same
 *    narrowed projection the prompt builder advertises, so prompt and validator
 *    never diverge
 *  - notes: when present, must be an array of non-empty strings
 *  - missing required entities do NOT fail validation (handled later in the
 *    proposal pipeline)
 *  - low confidence does NOT fail validation
 */
class LlmStructuredOutputValidator
{
    private const ALLOWED_ROOT_KEYS = ['intent', 'confidence', 'entities', 'notes'];

    /**
     * Root-level keys that an LLM must never emit. Any of these is a
     * contract violation — even if the value would have been ignored
     * downstream — because they signal the model attempted to drive
     * lifecycle, execution, or unsafe resolution.
     */
    private const FORBIDDEN_ROOT_KEYS = [
        'status',
        'proposal_status',
        'needs_confirmation',
        'confirmed_at',
        'executed_at',
        'execution_result',
        'action',
        'endpoint',
        'sql',
        'model',
        'id',
        'lead_id',
        'task_id',
        'user_id',
        'proposal_id',
        'changes',
        'mutations',
        'execute',
        'confirm',
        'tool_calls',
        'function_call',
    ];

    /**
     * Entity-key names the LLM must never emit. The LLM works in
     * user-facing labels; resolved IDs and internal references are forbidden.
     */
    private const FORBIDDEN_ENTITY_KEYS = [
        'id',
        'lead_id',
        'task_id',
        'user_id',
        'proposal_id',
        'endpoint',
        'sql',
        'model',
    ];

    public function __construct(private readonly InterpretationGrammar $grammar) {}

    /**
     * @param array<int|string, mixed> $payload
     */
    public function validate(array $payload): LlmStructuredOutputValidationResult
    {
        $errors = [];

        if (! $this->isAssociative($payload)) {
            return LlmStructuredOutputValidationResult::invalid(
                ['Root payload must be a JSON object.'],
            );
        }

        $this->validateRootKeys($payload, $errors);

        $intentValid    = $this->validateIntent($payload, $errors);
        $this->validateConfidence($payload, $errors);
        $entitiesValid  = $this->validateEntitiesShape($payload, $errors);
        $this->validateNotes($payload, $errors);

        if ($entitiesValid) {
            $this->validateEntityValues($payload['entities'], $errors);
        }

        if ($intentValid && $entitiesValid && $errors === []) {
            $this->validateIntentEntityCompatibility($payload, $errors);
        }

        return $errors === []
            ? LlmStructuredOutputValidationResult::valid()
            : LlmStructuredOutputValidationResult::invalid($errors);
    }

    /**
     * @param array<int|string, mixed> $payload
     *
     * @throws InvalidLlmStructuredOutputException
     */
    public function validateOrFail(array $payload): void
    {
        $result = $this->validate($payload);

        if (! $result->valid) {
            throw new InvalidLlmStructuredOutputException($result->errors);
        }
    }

    // ── Internal rules ───────────────────────────────────────────────────────

    /**
     * @param array<int|string, mixed> $payload
     * @param list<string>             $errors
     */
    private function validateRootKeys(array $payload, array &$errors): void
    {
        foreach (array_keys($payload) as $key) {
            if (! is_string($key)) {
                $errors[] = 'Root keys must be strings.';
                continue;
            }

            if (in_array($key, self::FORBIDDEN_ROOT_KEYS, true)) {
                $errors[] = "Root key [{$key}] is forbidden in LLM structured output.";
                continue;
            }

            if (! in_array($key, self::ALLOWED_ROOT_KEYS, true)) {
                $errors[] = "Root key [{$key}] is not part of the LLM structured output contract.";
            }
        }
    }

    /**
     * @param array<int|string, mixed> $payload
     * @param list<string>             $errors
     */
    private function validateIntent(array $payload, array &$errors): bool
    {
        if (! array_key_exists('intent', $payload)) {
            $errors[] = 'Field [intent] is required.';
            return false;
        }

        $intent = $payload['intent'];

        if (! is_string($intent) || trim($intent) === '') {
            $errors[] = 'Field [intent] must be a non-empty string.';
            return false;
        }

        if ($intent === 'unknown') {
            return true;
        }

        if (! $this->grammar->hasIntent($intent)) {
            $errors[] = "Intent [{$intent}] is not registered.";
            return false;
        }

        return true;
    }

    /**
     * @param array<int|string, mixed> $payload
     * @param list<string>             $errors
     */
    private function validateConfidence(array $payload, array &$errors): void
    {
        if (! array_key_exists('confidence', $payload)) {
            $errors[] = 'Field [confidence] is required.';
            return;
        }

        $confidence = $payload['confidence'];

        if (! is_int($confidence) && ! is_float($confidence)) {
            $errors[] = 'Field [confidence] must be numeric.';
            return;
        }

        if ($confidence < 0.0 || $confidence > 1.0) {
            $errors[] = "Field [confidence] ({$confidence}) must be between 0.0 and 1.0.";
        }
    }

    /**
     * @param array<int|string, mixed> $payload
     * @param list<string>             $errors
     */
    private function validateEntitiesShape(array $payload, array &$errors): bool
    {
        if (! array_key_exists('entities', $payload)) {
            $errors[] = 'Field [entities] is required.';
            return false;
        }

        $entities = $payload['entities'];

        if (! is_array($entities) || ! $this->isAssociative($entities, allowEmpty: true)) {
            $errors[] = 'Field [entities] must be a JSON object.';
            return false;
        }

        return true;
    }

    /**
     * @param array<int|string, mixed> $entities
     * @param list<string>             $errors
     */
    private function validateEntityValues(array $entities, array &$errors): void
    {
        foreach ($entities as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                $errors[] = 'Entity keys must be non-empty strings.';
                continue;
            }

            if (in_array($key, self::FORBIDDEN_ENTITY_KEYS, true)) {
                $errors[] = "Entity key [{$key}] is forbidden in LLM structured output.";
                continue;
            }

            if (str_ends_with($key, '_id')) {
                $errors[] = "Entity key [{$key}] looks like a database identifier and is not allowed.";
                continue;
            }

            $this->validateEntityValue($key, $value, $errors);
        }
    }

    /**
     * @param list<string> $errors
     */
    private function validateEntityValue(string $key, mixed $value, array &$errors): void
    {
        if ($value === null) {
            $errors[] = "Entity [{$key}] must not be null.";
            return;
        }

        if (is_array($value)) {
            // Collection-valued entity: must be a non-empty list of scalars.
            // An empty collection carries no semantic value — the model should
            // omit the field entirely instead.
            if ($value === []) {
                $errors[] = "Entity [{$key}] must not be an empty array.";
                return;
            }

            if (! array_is_list($value)) {
                $errors[] = "Entity [{$key}] must not contain nested objects.";
                return;
            }

            foreach ($value as $i => $item) {
                if (is_array($item) || is_object($item)) {
                    $errors[] = "Entity [{$key}][{$i}] must be a scalar value.";
                    continue;
                }
                if ($item === null) {
                    $errors[] = "Entity [{$key}][{$i}] must not be null.";
                    continue;
                }
                if (is_string($item) && trim($item) === '') {
                    $errors[] = "Entity [{$key}][{$i}] must not be empty.";
                }
            }
            return;
        }

        if (is_object($value)) {
            $errors[] = "Entity [{$key}] must not contain nested objects.";
            return;
        }

        if (! is_scalar($value)) {
            $errors[] = "Entity [{$key}] must be a scalar value.";
            return;
        }

        if (is_string($value) && trim($value) === '') {
            $errors[] = "Entity [{$key}] must not be an empty string.";
        }
    }

    /**
     * @param array<int|string, mixed> $payload
     * @param list<string>             $errors
     */
    private function validateNotes(array $payload, array &$errors): void
    {
        if (! array_key_exists('notes', $payload)) {
            return;
        }

        $notes = $payload['notes'];

        if (! is_array($notes) || ($notes !== [] && ! array_is_list($notes))) {
            $errors[] = 'Field [notes] must be a list of strings.';
            return;
        }

        foreach ($notes as $i => $note) {
            if (! is_string($note) || trim($note) === '') {
                $errors[] = "Note [{$i}] must be a non-empty string.";
            }
        }
    }

    /**
     * @param array<int|string, mixed> $payload
     * @param list<string>             $errors
     */
    private function validateIntentEntityCompatibility(array $payload, array &$errors): void
    {
        $intent   = $payload['intent'];
        $entities = $payload['entities'];

        if ($intent === 'unknown') {
            if ($entities !== []) {
                $errors[] = 'Intent [unknown] must be paired with empty entities.';
            }
            return;
        }

        if (! $this->grammar->hasIntent($intent)) {
            return; // already reported by validateIntent
        }

        $allowed = $this->grammar->allowedEntityKeys($intent);

        foreach (array_keys($entities) as $key) {
            if (! in_array($key, $allowed, true)) {
                $errors[] = "Entity key [{$key}] is not compatible with intent [{$intent}].";
            }
        }
    }

    /**
     * @param array<int|string, mixed> $value
     */
    private function isAssociative(array $value, bool $allowEmpty = false): bool
    {
        if ($value === []) {
            return $allowEmpty;
        }

        return ! array_is_list($value);
    }
}
