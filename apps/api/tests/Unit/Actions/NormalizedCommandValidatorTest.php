<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\DTO\IntentDefinition;
use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Executors\CreateTaskActionExecutor;
use Fluxio\Actions\Registry\IntentRegistry;
use Fluxio\Actions\Validation\NormalizedCommandValidator;
use Tests\TestCase;

class NormalizedCommandValidatorTest extends TestCase
{
    private NormalizedCommandValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        // Use the application-registered registry so tests reflect actual registered intents.
        $this->validator = $this->app->make(NormalizedCommandValidator::class);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function command(
        string $intent = 'schedule_meeting',
        float $confidence = 0.7,
        array $entities = [],
        string $locale = 'en',
    ): NormalizedCommand {
        return new NormalizedCommand(
            intent: $intent,
            confidence: $confidence,
            sourceText: 'test',
            locale: $locale,
            entities: $entities,
        );
    }

    // ── intent validation ────────────────────────────────────────────────────

    public function test_valid_registered_intent_passes(): void
    {
        $result = $this->validator->validate($this->command('schedule_meeting'));

        $this->assertTrue($result->valid);
        $this->assertEmpty($result->errors);
    }

    public function test_empty_intent_fails(): void
    {
        $result = $this->validator->validate($this->command(intent: ''));

        $this->assertFalse($result->valid);
        $this->assertNotEmpty($result->errors);
        $this->assertStringContainsString('Intent must not be empty', $result->errors[0]);
    }

    public function test_whitespace_only_intent_fails(): void
    {
        $result = $this->validator->validate($this->command(intent: '   '));

        $this->assertFalse($result->valid);
    }

    public function test_unknown_intent_sentinel_passes(): void
    {
        // 'unknown' is a valid sentinel returned when no intent is recognized.
        $result = $this->validator->validate($this->command(intent: 'unknown', confidence: 0.3));

        $this->assertTrue($result->valid);
    }

    public function test_unregistered_non_empty_intent_fails(): void
    {
        $result = $this->validator->validate($this->command(intent: 'hallucinated_action'));

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('hallucinated_action', $result->errors[0]);
        $this->assertStringContainsString('not registered', $result->errors[0]);
    }

    public function test_validator_uses_intent_registry_not_hardcoded_list(): void
    {
        // Build an isolated registry with only one intent.
        $miniRegistry = new IntentRegistry;
        $miniRegistry->register(new IntentDefinition(
            intent: 'my_custom_intent',
            label: 'Custom',
            module: 'tasks',
            operation: 'create',
            requirements: [],
            executorClass: CreateTaskActionExecutor::class,
            confidence: 0.9,
        ));

        $validator = new NormalizedCommandValidator($miniRegistry);

        $this->assertTrue($validator->validate($this->command('my_custom_intent', 0.9))->valid);
        $this->assertFalse($validator->validate($this->command('schedule_meeting', 0.7))->valid);
    }

    // ── confidence validation ─────────────────────────────────────────────────

    public function test_confidence_zero_passes(): void
    {
        $result = $this->validator->validate($this->command(confidence: 0.0));

        $this->assertTrue($result->valid);
    }

    public function test_confidence_one_passes(): void
    {
        $result = $this->validator->validate($this->command(confidence: 1.0));

        $this->assertTrue($result->valid);
    }

    public function test_confidence_above_one_fails(): void
    {
        $result = $this->validator->validate($this->command(confidence: 1.5));

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('Confidence', $result->errors[0]);
        $this->assertStringContainsString('1.5', $result->errors[0]);
    }

    public function test_confidence_below_zero_fails(): void
    {
        $result = $this->validator->validate($this->command(confidence: -0.1));

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('Confidence', $result->errors[0]);
    }

    // ── entity structural validation ──────────────────────────────────────────

    public function test_entity_with_null_value_fails(): void
    {
        $result = $this->validator->validate($this->command(
            entities: ['lead' => null],
        ));

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('non-null', $result->errors[0]);
    }

    public function test_entity_with_empty_string_value_fails(): void
    {
        $result = $this->validator->validate($this->command(
            entities: ['lead' => ''],
        ));

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('empty string', $result->errors[0]);
    }

    public function test_entity_with_valid_string_value_passes(): void
    {
        $result = $this->validator->validate($this->command(
            intent: 'schedule_meeting',
            entities: ['lead_query' => 'Rossini', 'date' => '2026-05-11', 'time' => '09:00'],
        ));

        $this->assertTrue($result->valid);
    }

    // ── missing required entity does NOT fail structural validation ───────────

    public function test_missing_required_entity_does_not_fail_validation(): void
    {
        // schedule_meeting requires lead, date, and time — but the command
        // may omit all of them. Missing fields are proposal-level concerns, not
        // structural validation concerns.
        $result = $this->validator->validate($this->command(
            intent: 'schedule_meeting',
            entities: [],
        ));

        $this->assertTrue($result->valid);
    }

    public function test_missing_required_entity_is_handled_at_proposal_level(): void
    {
        // assign_lead requires lead and assignee — both absent is structurally valid.
        $result = $this->validator->validate($this->command(
            intent: 'assign_lead',
            entities: [],
        ));

        $this->assertTrue($result->valid);
    }

    // ── entity key compatibility ───────────────────────────────────────────────

    public function test_entity_key_matching_requirement_key_passes(): void
    {
        // 'date' and 'time' are requirement keys for schedule_meeting.
        $result = $this->validator->validate($this->command(
            intent: 'schedule_meeting',
            entities: ['date' => '2026-05-11', 'time' => '09:00'],
        ));

        $this->assertTrue($result->valid);
    }

    public function test_entity_key_matching_requirement_entity_type_passes(): void
    {
        // 'lead_query' is the entityType of the lead requirement — the pre-resolution transit key.
        $result = $this->validator->validate($this->command(
            intent: 'schedule_meeting',
            entities: ['lead_query' => 'Rossini'],
        ));

        $this->assertTrue($result->valid);
    }

    public function test_entity_key_incompatible_with_intent_fails(): void
    {
        // 'invoice_id' is not declared in any schedule_meeting requirement.
        $result = $this->validator->validate($this->command(
            intent: 'schedule_meeting',
            entities: ['invoice_id' => 'INV-999'],
        ));

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('invoice_id', $result->errors[0]);
        $this->assertStringContainsString('schedule_meeting', $result->errors[0]);
    }

    public function test_date_and_time_pass_for_create_task_via_universal_parser_keys(): void
    {
        // DateTimeExpressionParser emits 'date' and 'time' for all intents, including
        // create_task which uses 'due_at' as its canonical date key. These keys are
        // permitted via the UNIVERSAL_PARSER_KEYS transitional allowlist.
        $result = $this->validator->validate($this->command(
            intent: 'create_task',
            entities: ['lead_query' => 'Rossini', 'date' => '2026-05-11', 'time' => '09:00'],
        ));

        $this->assertTrue($result->valid);
    }

    public function test_unknown_intent_skips_entity_compatibility_check(): void
    {
        // 'unknown' has no definition — entity compatibility cannot be checked.
        $result = $this->validator->validate($this->command(
            intent: 'unknown',
            entities: ['anything' => 'goes'],
            confidence: 0.3,
        ));

        $this->assertTrue($result->valid);
    }

    // ── value-shape validation (Phase 9E.4) ──────────────────────────────────

    /**
     * @return iterable<string, array{array<string,mixed>, bool}>
     */
    public static function valueShapeProvider(): iterable
    {
        // [entities, expectedValid]
        yield 'valid iso date + hh:mm time' => [['date' => '2026-06-02', 'time' => '10:00'], true];
        yield 'time lower bound 00:00' => [['time' => '00:00'], true];
        yield 'time upper bound 23:59' => [['time' => '23:59'], true];

        yield 'date expression word' => [['date' => 'tomorrow'], false];
        yield 'date impossible calendar day' => [['date' => '2026-02-30'], false];
        yield 'date wrong separator format' => [['date' => '06/02/2026'], false];
        yield 'date not zero padded' => [['date' => '2026-6-2'], false];

        yield 'time hour only' => [['time' => '10'], false];
        yield 'time 24 oclock' => [['time' => '24:00'], false];
        yield 'time am pm' => [['time' => '9am'], false];
        yield 'time fuzzy phrase' => [['time' => 'morning-ish'], false];
        yield 'time with seconds' => [['time' => '10:00:00'], false];
    }

    /**
     * @param  array<string, mixed>  $entities
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('valueShapeProvider')]
    public function test_normalized_date_time_value_shapes(array $entities, bool $expectedValid): void
    {
        // schedule_meeting allows both the canonical date/time keys and the raw
        // date_expression/time_expression keys.
        $result = $this->validator->validate($this->command('schedule_meeting', 0.7, $entities));

        $this->assertSame($expectedValid, $result->valid, implode('; ', $result->errors));
    }

    public function test_raw_expression_keys_are_not_shape_validated(): void
    {
        // date_expression / time_expression are interpretation-side raw expressions —
        // allowed for schedule_meeting and intentionally NOT shape-constrained.
        $result = $this->validator->validate($this->command('schedule_meeting', 0.7, [
            'date_expression' => 'tomorrow',
            'time_expression' => 'morning-ish',
        ]));

        $this->assertTrue($result->valid, implode('; ', $result->errors));
    }

    public function test_malformed_time_error_message_is_explicit(): void
    {
        $result = $this->validator->validate($this->command('schedule_meeting', 0.7, ['time' => 'morning-ish']));

        $this->assertFalse($result->valid);
        $this->assertStringContainsString('time', $result->errors[0]);
        $this->assertStringContainsString('morning-ish', $result->errors[0]);
        $this->assertStringContainsString('HH:MM', $result->errors[0]);
    }

    // ── multiple errors ───────────────────────────────────────────────────────

    public function test_multiple_validation_errors_are_collected(): void
    {
        $result = $this->validator->validate(new NormalizedCommand(
            intent: '',
            confidence: 2.0,
            sourceText: 'test',
            locale: 'en',
        ));

        $this->assertFalse($result->valid);
        $this->assertGreaterThanOrEqual(2, count($result->errors));
    }
}
