<?php

namespace Tests\Unit\Actions;

use Carbon\Carbon;
use Fluxio\Actions\Services\ActionInterpreterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoLeads;
use Tests\TestCase;

/**
 * Structured temporal explainability on editable date/time fields.
 *
 * Verifies that scheduling proposals attach parser-local explanation metadata
 * (source / expression / confidence / message) to date/time editable fields,
 * that the metadata never appears on non-temporal fields, and that lifecycle
 * behaviour (status / readiness / warnings) is unchanged. The explanation
 * confidence is parser-local only and is asserted alongside, but it does NOT
 * gate readiness, validation, confirmation, or execution.
 */
class TemporalFieldExplanationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoLeads;

    private ActionInterpreterService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-05-10 08:00:00');
        $this->service = $this->app->make(ActionInterpreterService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function field(array $editableFields, string $key): ?object
    {
        foreach ($editableFields as $f) {
            if ($f->key === $key) {
                return $f;
            }
        }

        return null;
    }

    public function test_schedule_call_relative_date_field_carries_structured_explanation(): void
    {
        $proposal = $this->service->interpret('Schedule a call with Rossini tomorrow at 3pm');

        $dateField = $this->field($proposal->editable_fields, 'date');
        $this->assertNotNull($dateField->explanation);
        $this->assertSame('relative', $dateField->explanation->source);
        $this->assertSame('tomorrow', $dateField->explanation->expression);
        $this->assertSame(0.9, $dateField->explanation->confidence);
        $this->assertSame("Date resolved from 'tomorrow'.", $dateField->explanation->message);

        // Serialized payload exposes the explanation object.
        $serialized = $dateField->toArray();
        $this->assertArrayHasKey('explanation', $serialized);
        $this->assertSame("Date resolved from 'tomorrow'.", $serialized['explanation']['message']);
    }

    public function test_schedule_call_day_part_time_field_carries_structured_explanation(): void
    {
        $proposal = $this->service->interpret('Schedule a call with Rossini tomorrow afternoon');

        $timeField = $this->field($proposal->editable_fields, 'time');
        $this->assertNotNull($timeField->explanation);
        $this->assertSame('day_part', $timeField->explanation->source);
        $this->assertSame('afternoon', $timeField->explanation->expression);
        $this->assertSame(0.75, $timeField->explanation->confidence);
        $this->assertSame("Time inferred from 'afternoon' as 15:00.", $timeField->explanation->message);
    }

    public function test_day_part_field_message_matches_inferred_time_warning(): void
    {
        // Phase 6C warning behaviour is preserved AND the field note matches it verbatim.
        $proposal = $this->service->interpret('Schedule a call with Rossini tomorrow afternoon');

        $timeField = $this->field($proposal->editable_fields, 'time');
        $this->assertContains($timeField->explanation->message, $proposal->warnings);
        $this->assertContains("Time inferred from 'afternoon' as 15:00.", $proposal->warnings);
    }

    public function test_schedule_meeting_temporal_fields_carry_explanations(): void
    {
        // schedule_meeting is built from the intent definition, not the bespoke
        // schedule_call builder — explanations must flow through that path too.
        $proposal = $this->service->interpret('Schedule a meeting with Rossini next Friday at 10:30');

        $this->assertSame('schedule_meeting', $proposal->intent);

        $dateField = $this->field($proposal->editable_fields, 'date');
        $this->assertNotNull($dateField->explanation);
        $this->assertSame('weekday', $dateField->explanation->source);
        $this->assertSame('next friday', $dateField->explanation->expression);

        $timeField = $this->field($proposal->editable_fields, 'time');
        $this->assertNotNull($timeField->explanation);
        $this->assertSame('explicit', $timeField->explanation->source);
        $this->assertSame('at 10:30', $timeField->explanation->expression);
        $this->assertSame("Time set from 'at 10:30' as 10:30.", $timeField->explanation->message);
    }

    public function test_non_temporal_field_has_no_explanation_and_omits_key_when_serialized(): void
    {
        $proposal = $this->service->interpret('Schedule a call with Rossini tomorrow afternoon');

        $leadField = $this->field($proposal->editable_fields, 'lead');
        $this->assertNotNull($leadField);
        $this->assertNull($leadField->explanation);

        // No empty explanation object is emitted in the serialized payload.
        $this->assertArrayNotHasKey('explanation', $leadField->toArray());
    }

    public function test_missing_temporal_field_has_no_explanation(): void
    {
        // No time in the source text → time field is "missing" and carries no explanation.
        $proposal = $this->service->interpret('Schedule a call with Rossini tomorrow');

        $timeField = $this->field($proposal->editable_fields, 'time');
        $this->assertSame('missing', $timeField->source);
        $this->assertNull($timeField->explanation);
        $this->assertArrayNotHasKey('explanation', $timeField->toArray());
    }

    public function test_explanation_does_not_change_lifecycle_status_or_readiness(): void
    {
        // Same readiness outcomes as before the explainability metadata existed.
        $ready = $this->service->interpret('Schedule a call with Rossini tomorrow afternoon');
        $this->assertSame('ready', $ready->status);

        $draft = $this->service->interpret('Schedule a call with Rossini tomorrow');
        $this->assertSame('draft', $draft->status);
    }
}
