<?php

namespace Tests\Unit\Actions\Llm;

use Fluxio\Actions\Llm\Exceptions\InvalidLlmStructuredOutputException;
use Fluxio\Actions\Llm\Validation\LlmStructuredOutputValidator;
use Tests\TestCase;

/**
 * Unit tests for the Phase 3 LLM structured-output validator.
 *
 * Exercises the JSON-contract boundary only. No real LLM call is made,
 * no proposal is built, no entity is resolved, and NormalizedCommandValidator
 * is never invoked here — it remains the next boundary downstream.
 */
class LlmStructuredOutputValidatorTest extends TestCase
{
    private LlmStructuredOutputValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = $this->app->make(LlmStructuredOutputValidator::class);
    }

    private function assertValid(array $payload): void
    {
        $result = $this->validator->validate($payload);
        $this->assertTrue(
            $result->valid,
            'Expected payload to be valid; errors: ' . implode(' | ', $result->errors),
        );
    }

    private function assertInvalid(array $payload, ?string $errorContains = null): void
    {
        $result = $this->validator->validate($payload);
        $this->assertFalse($result->valid, 'Expected payload to be invalid.');

        if ($errorContains !== null) {
            $joined = implode(' | ', $result->errors);
            $this->assertStringContainsString($errorContains, $joined);
        }
    }

    // 1
    public function test_accepts_valid_create_task_structured_output(): void
    {
        $this->assertValid([
            'intent'     => 'create_task',
            'confidence' => 0.82,
            'entities'   => ['lead' => 'Rossi', 'due_at' => 'tomorrow', 'priority' => 'high'],
            'notes'      => [],
        ]);
    }

    // 2
    public function test_accepts_unknown_intent_with_low_confidence_and_empty_entities(): void
    {
        $this->assertValid([
            'intent'     => 'unknown',
            'confidence' => 0.1,
            'entities'   => [],
        ]);
    }

    // 3
    public function test_rejects_non_associative_root_payload(): void
    {
        $this->assertInvalid(
            ['create_task', 0.9, []],
            'Root payload must be a JSON object.',
        );
    }

    // 4
    public function test_rejects_missing_intent(): void
    {
        $this->assertInvalid(
            ['confidence' => 0.5, 'entities' => []],
            'Field [intent] is required.',
        );
    }

    // 5
    public function test_rejects_empty_intent(): void
    {
        $this->assertInvalid(
            ['intent' => '   ', 'confidence' => 0.5, 'entities' => []],
            'Field [intent] must be a non-empty string.',
        );
    }

    // 6
    public function test_rejects_unregistered_intent(): void
    {
        $this->assertInvalid(
            ['intent' => 'delete_universe', 'confidence' => 0.9, 'entities' => []],
            'Intent [delete_universe] is not registered.',
        );
    }

    // 7
    public function test_rejects_missing_confidence(): void
    {
        $this->assertInvalid(
            ['intent' => 'create_task', 'entities' => []],
            'Field [confidence] is required.',
        );
    }

    // 8
    public function test_rejects_confidence_below_zero(): void
    {
        $this->assertInvalid(
            ['intent' => 'create_task', 'confidence' => -0.1, 'entities' => []],
            'must be between 0.0 and 1.0',
        );
    }

    // 9
    public function test_rejects_confidence_above_one(): void
    {
        $this->assertInvalid(
            ['intent' => 'create_task', 'confidence' => 1.5, 'entities' => []],
            'must be between 0.0 and 1.0',
        );
    }

    // 10
    public function test_rejects_missing_entities(): void
    {
        $this->assertInvalid(
            ['intent' => 'create_task', 'confidence' => 0.9],
            'Field [entities] is required.',
        );
    }

    // 11
    public function test_rejects_non_associative_entities(): void
    {
        $this->assertInvalid(
            ['intent' => 'create_task', 'confidence' => 0.9, 'entities' => ['just', 'a', 'list']],
            'Field [entities] must be a JSON object.',
        );
    }

    // 12
    public function test_rejects_unknown_root_keys(): void
    {
        $this->assertInvalid(
            [
                'intent'      => 'create_task',
                'confidence'  => 0.9,
                'entities'    => [],
                'mystery_key' => true,
            ],
            'Root key [mystery_key] is not part of the LLM structured output contract.',
        );
    }

    // 13
    public function test_rejects_forbidden_root_keys(): void
    {
        $this->assertInvalid(
            [
                'intent'           => 'create_task',
                'confidence'       => 0.9,
                'entities'         => [],
                'execution_result' => ['ok' => true],
            ],
            'Root key [execution_result] is forbidden',
        );

        $this->assertInvalid(
            [
                'intent'     => 'create_task',
                'confidence' => 0.9,
                'entities'   => [],
                'tool_calls' => [],
            ],
            'Root key [tool_calls] is forbidden',
        );
    }

    // 14
    public function test_rejects_entity_keys_ending_with_id(): void
    {
        $this->assertInvalid(
            [
                'intent'     => 'create_task',
                'confidence' => 0.9,
                'entities'   => ['contact_id' => 'X'],
            ],
            'Entity key [contact_id] looks like a database identifier',
        );
    }

    // 15
    public function test_rejects_forbidden_entity_keys(): void
    {
        $this->assertInvalid(
            [
                'intent'     => 'create_task',
                'confidence' => 0.9,
                'entities'   => ['lead_id' => 42],
            ],
            'Entity key [lead_id] is forbidden',
        );
    }

    // 16
    public function test_rejects_null_entity_values(): void
    {
        $this->assertInvalid(
            [
                'intent'     => 'create_task',
                'confidence' => 0.9,
                'entities'   => ['lead' => null],
            ],
            'Entity [lead] must not be null.',
        );
    }

    // 17
    public function test_rejects_empty_string_entity_values(): void
    {
        $this->assertInvalid(
            [
                'intent'     => 'create_task',
                'confidence' => 0.9,
                'entities'   => ['lead' => '   '],
            ],
            'must not be an empty string',
        );
    }

    // 18
    public function test_rejects_incompatible_entity_keys_for_intent(): void
    {
        $this->assertInvalid(
            [
                'intent'     => 'create_task',
                'confidence' => 0.9,
                'entities'   => ['location' => 'Milano'],
            ],
            'Entity key [location] is not compatible with intent [create_task].',
        );
    }

    // 19
    public function test_accepts_transitional_date_and_time_keys(): void
    {
        $this->assertValid([
            'intent'     => 'create_task',
            'confidence' => 0.9,
            'entities'   => ['date' => '2026-05-10', 'time' => '10:30'],
        ]);
    }

    // 20
    public function test_accepts_missing_required_entities(): void
    {
        // schedule_call requires lead/date/time; omitting them must NOT fail.
        $this->assertValid([
            'intent'     => 'schedule_call',
            'confidence' => 0.6,
            'entities'   => [],
        ]);
    }

    // 21
    public function test_rejects_notes_when_not_array(): void
    {
        $this->assertInvalid(
            [
                'intent'     => 'create_task',
                'confidence' => 0.9,
                'entities'   => [],
                'notes'      => 'just a string',
            ],
            'Field [notes] must be a list of strings.',
        );
    }

    // 22
    public function test_rejects_empty_note_strings(): void
    {
        $this->assertInvalid(
            [
                'intent'     => 'create_task',
                'confidence' => 0.9,
                'entities'   => [],
                'notes'      => ['ok', '   '],
            ],
            'Note [1] must be a non-empty string.',
        );
    }

    // 23
    public function test_accepts_array_entity_values_when_all_scalars_and_non_empty(): void
    {
        $this->assertValid([
            'intent'     => 'schedule_meeting',
            'confidence' => 0.85,
            'entities'   => [
                'lead'         => 'Rossi SRL',
                'date'         => 'tomorrow',
                'time'         => '10:30',
                'participants' => ['Mario', 'Luca'],
            ],
        ]);

        $this->assertInvalid(
            [
                'intent'     => 'schedule_meeting',
                'confidence' => 0.85,
                'entities'   => ['participants' => ['Mario', '']],
            ],
            'Entity [participants][1] must not be empty.',
        );
    }

    // 24
    public function test_rejects_nested_object_entity_values(): void
    {
        $this->assertInvalid(
            [
                'intent'     => 'create_task',
                'confidence' => 0.9,
                'entities'   => ['lead' => ['nested' => 'value']],
            ],
            'Entity [lead] must not contain nested objects.',
        );
    }

    // ── validateOrFail throws ──────────────────────────────────────────

    public function test_validate_or_fail_throws_invalid_structured_output_exception(): void
    {
        $this->expectException(InvalidLlmStructuredOutputException::class);

        $this->validator->validateOrFail([
            'intent'     => 'create_task',
            'confidence' => 2.0,
            'entities'   => [],
        ]);
    }

    public function test_validate_or_fail_is_silent_on_valid_payload(): void
    {
        $this->validator->validateOrFail([
            'intent'     => 'create_task',
            'confidence' => 0.9,
            'entities'   => ['lead' => 'Rossi'],
        ]);

        $this->assertTrue(true);
    }

    // ── Phase 3 hardening ──────────────────────────────────────────────

    public function test_rejects_empty_array_entity_values(): void
    {
        $this->assertInvalid(
            [
                'intent'     => 'schedule_meeting',
                'confidence' => 0.85,
                'entities'   => ['participants' => []],
            ],
            'Entity [participants] must not be an empty array.',
        );
    }

    public function test_rejects_unknown_intent_with_non_empty_entities(): void
    {
        $this->assertInvalid(
            [
                'intent'     => 'unknown',
                'confidence' => 0.2,
                'entities'   => ['lead' => 'Rossi'],
            ],
            'Intent [unknown] must be paired with empty entities.',
        );
    }

    public function test_rejects_string_confidence_values(): void
    {
        $this->assertInvalid(
            [
                'intent'     => 'create_task',
                'confidence' => '0.82',
                'entities'   => [],
            ],
            'Field [confidence] must be numeric.',
        );
    }

    public function test_exception_exposes_validation_errors(): void
    {
        try {
            $this->validator->validateOrFail([
                'intent'     => 'create_task',
                'confidence' => 2.0,
                'entities'   => ['lead_id' => 42],
            ]);
            $this->fail('Expected InvalidLlmStructuredOutputException.');
        } catch (InvalidLlmStructuredOutputException $e) {
            $this->assertNotEmpty($e->errors);
            $joined = implode(' | ', $e->errors);
            $this->assertStringContainsString('between 0.0 and 1.0', $joined);
            $this->assertStringContainsString('Entity key [lead_id] is forbidden', $joined);
        }
    }
}
