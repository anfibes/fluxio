<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\DTO\SemanticRefinementMutation;
use Fluxio\Actions\Enums\SemanticMutationType;
use Fluxio\Actions\Exceptions\CannotLowerSemanticRefinementException;
use Fluxio\Actions\Support\SemanticRefinementLowerer;
use PHPUnit\Framework\TestCase;

/**
 * Phase 8B: the Semantic Refinement IR (interpretation boundary) lowers
 * deterministically into structural NormalizedMutation (application boundary).
 *
 * These tests pin the lowering contract and prove an un-lowerable semantic
 * operation is rejected, never silently applied. Pure unit — no DB, no runtime.
 */
class SemanticRefinementLowererTest extends TestCase
{
    private SemanticRefinementLowerer $lowerer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lowerer = new SemanticRefinementLowerer;
    }

    public function test_semantic_mutation_can_represent_replace_time(): void
    {
        $semantic = new SemanticRefinementMutation(
            type: SemanticMutationType::ReplaceTime,
            payload: ['value' => '11:00'],
        );

        $this->assertSame(SemanticMutationType::ReplaceTime, $semantic->type);
        $this->assertSame('11:00', $semantic->payload['value']);
    }

    public function test_semantic_mutation_can_represent_shift_time_payload(): void
    {
        $semantic = new SemanticRefinementMutation(
            type: SemanticMutationType::ShiftTime,
            payload: ['amount' => 30, 'unit' => 'minutes', 'direction' => 'later'],
        );

        $this->assertSame(30, $semantic->payload['amount']);
        $this->assertSame('minutes', $semantic->payload['unit']);
        $this->assertSame('later', $semantic->payload['direction']);
    }

    public function test_lowers_replace_time_into_structural_time_replace(): void
    {
        $mutation = $this->lowerer->lower(new SemanticRefinementMutation(
            type: SemanticMutationType::ReplaceTime,
            payload: ['value' => '11:00'],
        ));

        $this->assertSame('time', $mutation->field);
        $this->assertSame('replace', $mutation->operation);
        $this->assertSame('11:00', $mutation->value);
        $this->assertNull($mutation->target);
        $this->assertSame(SemanticMutationType::ReplaceTime, $mutation->semanticType());
    }

    public function test_lowers_replace_date_into_structural_date_replace(): void
    {
        $mutation = $this->lowerer->lower(new SemanticRefinementMutation(
            type: SemanticMutationType::ReplaceDate,
            payload: ['value' => '2026-06-05'],
        ));

        $this->assertSame('date', $mutation->field);
        $this->assertSame('replace', $mutation->operation);
        $this->assertSame('2026-06-05', $mutation->value);
        $this->assertSame(SemanticMutationType::ReplaceDate, $mutation->semanticType());
    }

    public function test_lowers_shift_time_into_metadata_only_contextual_mutation(): void
    {
        $mutation = $this->lowerer->lower(new SemanticRefinementMutation(
            type: SemanticMutationType::ShiftTime,
            payload: ['amount' => 30, 'unit' => 'minutes', 'direction' => 'later'],
        ));

        // Matches RuleBasedRefinementInterpreter::extractTemporalShift exactly:
        // value stays null (the service computes the concrete time from context).
        $this->assertSame('time', $mutation->field);
        $this->assertSame('replace', $mutation->operation);
        $this->assertNull($mutation->value);
        $this->assertSame('temporal_shift', $mutation->metadata['contextual_operation']);
        $this->assertSame('minutes', $mutation->metadata['unit']);
        $this->assertSame(30, $mutation->metadata['amount']);
        $this->assertSame('later', $mutation->metadata['direction']);
        $this->assertSame(SemanticMutationType::ShiftTime, $mutation->semanticType());
    }

    public function test_shift_time_normalizes_hours_to_minutes(): void
    {
        $mutation = $this->lowerer->lower(new SemanticRefinementMutation(
            type: SemanticMutationType::ShiftTime,
            payload: ['amount' => 1, 'unit' => 'hours', 'direction' => 'earlier'],
        ));

        $this->assertSame('minutes', $mutation->metadata['unit']);
        $this->assertSame(60, $mutation->metadata['amount']);
        $this->assertSame('earlier', $mutation->metadata['direction']);
    }

    public function test_lowers_add_participant_into_participants_append(): void
    {
        $mutation = $this->lowerer->lower(new SemanticRefinementMutation(
            type: SemanticMutationType::AddParticipant,
            payload: ['value' => 'Marco'],
        ));

        $this->assertSame('participants', $mutation->field);
        $this->assertSame('append', $mutation->operation);
        $this->assertSame('Marco', $mutation->value);
        $this->assertNull($mutation->target);
        $this->assertSame(SemanticMutationType::AddParticipant, $mutation->semanticType());
    }

    public function test_lowers_remove_participant_into_participants_remove(): void
    {
        $mutation = $this->lowerer->lower(new SemanticRefinementMutation(
            type: SemanticMutationType::RemoveParticipant,
            target: 'Mario',
        ));

        $this->assertSame('participants', $mutation->field);
        $this->assertSame('remove', $mutation->operation);
        $this->assertNull($mutation->value);
        $this->assertSame('Mario', $mutation->target);
        $this->assertSame(SemanticMutationType::RemoveParticipant, $mutation->semanticType());
    }

    public function test_lowers_replace_participant_into_targeted_participants_replace(): void
    {
        $mutation = $this->lowerer->lower(new SemanticRefinementMutation(
            type: SemanticMutationType::ReplaceParticipant,
            payload: ['value' => 'Mario'],
            target: 'Marco',
        ));

        $this->assertSame('participants', $mutation->field);
        $this->assertSame('replace', $mutation->operation);
        $this->assertSame('Mario', $mutation->value);
        $this->assertSame('Marco', $mutation->target);
        $this->assertSame(SemanticMutationType::ReplaceParticipant, $mutation->semanticType());
    }

    public function test_unknown_semantic_type_is_rejected(): void
    {
        $this->expectException(CannotLowerSemanticRefinementException::class);
        $this->lowerer->lower(new SemanticRefinementMutation(type: SemanticMutationType::Unknown));
    }

    public function test_replace_time_without_value_is_rejected(): void
    {
        $this->expectException(CannotLowerSemanticRefinementException::class);
        $this->lowerer->lower(new SemanticRefinementMutation(type: SemanticMutationType::ReplaceTime));
    }

    public function test_shift_time_without_valid_amount_is_rejected(): void
    {
        $this->expectException(CannotLowerSemanticRefinementException::class);
        $this->lowerer->lower(new SemanticRefinementMutation(
            type: SemanticMutationType::ShiftTime,
            payload: ['unit' => 'minutes'],
        ));
    }

    public function test_shift_time_with_invalid_direction_is_rejected(): void
    {
        $this->expectException(CannotLowerSemanticRefinementException::class);
        $this->lowerer->lower(new SemanticRefinementMutation(
            type: SemanticMutationType::ShiftTime,
            payload: ['amount' => 30, 'unit' => 'minutes', 'direction' => 'sideways'],
        ));
    }

    public function test_replace_participant_without_target_is_rejected(): void
    {
        $this->expectException(CannotLowerSemanticRefinementException::class);
        $this->lowerer->lower(new SemanticRefinementMutation(
            type: SemanticMutationType::ReplaceParticipant,
            payload: ['value' => 'Mario'],
        ));
    }
}
