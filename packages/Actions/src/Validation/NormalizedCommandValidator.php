<?php

namespace Fluxio\Actions\Validation;

use Fluxio\Actions\DTO\IntentDefinition;
use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Registry\IntentRegistry;

class NormalizedCommandValidator
{
    /**
     * Entity keys emitted by DateTimeExpressionParser for any intent, independent of that
     * intent's specific requirements. Transitional: when intent-specific date/time fields
     * are formally declared in every IntentDefinition this list can be removed.
     */
    private const UNIVERSAL_PARSER_KEYS = ['date', 'time'];

    /**
     * Canonical, already-interpreted runtime value keys that must carry a well-formed
     * value, not a raw expression (Phase 9E.4). These are validated for SHAPE so a
     * future probabilistic provider cannot leak `date: "next week"` /
     * `time: "morning-ish"` into proposal building. The raw `*_expression` keys
     * (date_expression, time_expression) are intentionally NOT in this set — they are
     * interpretation-side user-facing expressions and stay unconstrained.
     */
    private const SHAPE_VALIDATED_KEYS = ['date', 'time'];

    public function __construct(private readonly IntentRegistry $registry) {}

    public function validate(NormalizedCommand $command): NormalizedCommandValidationResult
    {
        $errors = [];

        $this->validateIntent($command, $errors);
        $this->validateConfidence($command, $errors);
        $this->validateEntities($command, $errors);
        $this->validateEntityValueShapes($command, $errors);

        // Entity key compatibility can only run when intent is structurally valid.
        if (empty($errors)) {
            $this->validateEntityCompatibility($command, $errors);
        }

        return empty($errors)
            ? NormalizedCommandValidationResult::valid()
            : NormalizedCommandValidationResult::invalid($errors);
    }

    // ── Validation rules ─────────────────────────────────────────────────────

    private function validateIntent(NormalizedCommand $command, array &$errors): void
    {
        if (trim($command->intent) === '') {
            $errors[] = 'Intent must not be empty.';

            return;
        }

        // 'unknown' is a valid sentinel returned when no intent is recognized.
        // It is not registered in IntentRegistry by design.
        if ($command->intent === 'unknown') {
            return;
        }

        if ($this->registry->find($command->intent) === null) {
            $errors[] = "Intent [{$command->intent}] is not registered.";
        }
    }

    private function validateConfidence(NormalizedCommand $command, array &$errors): void
    {
        if ($command->confidence < 0.0 || $command->confidence > 1.0) {
            $errors[] = "Confidence [{$command->confidence}] must be between 0.0 and 1.0.";
        }
    }

    private function validateEntities(NormalizedCommand $command, array &$errors): void
    {
        foreach ($command->entities as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                $errors[] = 'Each entity key must be a non-empty string.';

                continue;
            }

            if ($value === null) {
                $errors[] = "Entity [{$key}] must have a non-null value.";

                continue;
            }

            if ($value === '') {
                $errors[] = "Entity [{$key}] must not have an empty string value.";
            }
        }
    }

    /**
     * Strict value-shape validation for the normalized runtime date/time keys
     * (Phase 9E.4). It runs independently of intent: `date` must be a valid ISO local
     * calendar date (YYYY-MM-DD) and `time` a 24h HH:MM (00:00–23:59). Null/empty
     * values are already reported by validateEntities and are skipped here to avoid
     * duplicate errors. Raw `*_expression` keys are never shape-checked.
     */
    private function validateEntityValueShapes(NormalizedCommand $command, array &$errors): void
    {
        foreach (self::SHAPE_VALIDATED_KEYS as $key) {
            if (! array_key_exists($key, $command->entities)) {
                continue;
            }

            $value = $command->entities[$key];

            if ($value === null || $value === '') {
                continue;
            }

            $valid = match ($key) {
                'date' => is_string($value) && $this->isIsoDate($value),
                'time' => is_string($value) && $this->isHourMinute($value),
                default => true,
            };

            if (! $valid) {
                $display = is_string($value) ? $value : gettype($value);
                $expected = $key === 'date' ? 'ISO date (YYYY-MM-DD)' : '24h time (HH:MM)';
                $errors[] = "Entity [{$key}] value [{$display}] is not a valid {$expected}.";
            }
        }
    }

    /**
     * A valid ISO local calendar date: the literal YYYY-MM-DD shape AND a real
     * calendar date (so 2026-02-30, 2026-13-01, 06/02/2026, or 2026-6-2 are rejected).
     */
    private function isIsoDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    /**
     * A valid 24h time: HH:MM, 00:00 through 23:59 (rejects 24:00, 10, 9am,
     * 10:00:00, morning-ish).
     */
    private function isHourMinute(string $value): bool
    {
        return preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $value) === 1;
    }

    private function validateEntityCompatibility(NormalizedCommand $command, array &$errors): void
    {
        // 'unknown' has no definition — skip compatibility check.
        if ($command->intent === 'unknown') {
            return;
        }

        $definition = $this->registry->find($command->intent);

        if ($definition === null) {
            return;
        }

        $allowed = $this->allowedEntityKeys($definition);

        foreach (array_keys($command->entities) as $key) {
            if (! in_array($key, $allowed, true)) {
                $errors[] = "Entity key [{$key}] is not compatible with intent [{$command->intent}].";
            }
        }
    }

    /**
     * Returns all entity keys accepted for a given intent definition.
     *
     * Includes:
     * - EntityRequirement.key  — the canonical key used after entity resolution
     * - EntityRequirement.entityType — supports the pre-resolution transit pattern
     *   (e.g., lead_query emitted before entity resolution resolves it to lead)
     * - UNIVERSAL_PARSER_KEYS — transitional allowlist for keys that DateTimeExpressionParser
     *   may append to any intent's entities (date, time) before intent-specific field
     *   declarations are formalised in every IntentDefinition
     *
     * @return list<string>
     */
    private function allowedEntityKeys(IntentDefinition $definition): array
    {
        $keys = self::UNIVERSAL_PARSER_KEYS;

        foreach ($definition->requirements as $req) {
            $keys[] = $req->key;
            $keys[] = $req->entityType;
        }

        return array_values(array_unique($keys));
    }
}
