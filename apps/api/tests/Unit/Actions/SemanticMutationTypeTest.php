<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\DTO\NormalizedMutation;
use Fluxio\Actions\Enums\SemanticMutationType;
use PHPUnit\Framework\TestCase;

/**
 * Phase 7C: semantic mutation type is descriptive explainability metadata.
 * These unit tests pin the narrow first-slice classification rules. The enum
 * never drives lifecycle, execution, or capability decisions.
 */
class SemanticMutationTypeTest extends TestCase
{
    public function test_direct_time_replace_is_replace_time(): void
    {
        $mutation = new NormalizedMutation(field: 'time', label: 'Time', value: '10:30', operation: 'replace');

        $this->assertSame(SemanticMutationType::ReplaceTime, $mutation->semanticType());
    }

    public function test_direct_date_replace_is_replace_date(): void
    {
        $mutation = new NormalizedMutation(field: 'date', label: 'Date', value: '2026-05-31', operation: 'replace');

        $this->assertSame(SemanticMutationType::ReplaceDate, $mutation->semanticType());
    }

    public function test_temporal_shift_metadata_is_shift_time(): void
    {
        // A resolved contextual shift: a concrete time replace that still carries
        // the temporal_shift metadata must stay shift_time, not replace_time.
        $mutation = new NormalizedMutation(
            field: 'time',
            label: 'Time',
            value: '10:30',
            operation: 'replace',
            metadata: ['contextual_operation' => 'temporal_shift', 'amount' => 30, 'direction' => 'later'],
        );

        $this->assertSame(SemanticMutationType::ShiftTime, $mutation->semanticType());
    }

    public function test_explicit_semantic_type_overrides_classification(): void
    {
        $mutation = new NormalizedMutation(
            field: 'time',
            label: 'Time',
            value: '10:30',
            operation: 'replace',
            semanticType: SemanticMutationType::ShiftTime,
        );

        $this->assertSame(SemanticMutationType::ShiftTime, $mutation->semanticType());
    }

    public function test_unrelated_scalar_replace_is_unknown(): void
    {
        $mutation = new NormalizedMutation(field: 'priority', label: 'Priority', value: 'high', operation: 'replace');

        $this->assertSame(SemanticMutationType::Unknown, $mutation->semanticType());
    }

    public function test_clear_and_unrelated_collection_operations_are_unknown(): void
    {
        $clear = new NormalizedMutation(field: 'priority', label: 'Priority', value: null, operation: 'clear');
        // append on a non-participant field has no participant meaning.
        $otherAppend = new NormalizedMutation(field: 'tags', label: 'Tags', value: 'urgent', operation: 'append');

        $this->assertSame(SemanticMutationType::Unknown, $clear->semanticType());
        $this->assertSame(SemanticMutationType::Unknown, $otherAppend->semanticType());
    }

    // ── Phase 7D: participant collection mutations ──────────────────────────

    public function test_participant_append_is_add_participant(): void
    {
        $mutation = new NormalizedMutation(field: 'participants', label: 'Participants', value: 'Marco', operation: 'append');

        $this->assertSame(SemanticMutationType::AddParticipant, $mutation->semanticType());
    }

    public function test_participant_remove_is_remove_participant(): void
    {
        $mutation = new NormalizedMutation(field: 'participants', label: 'Participants', value: null, operation: 'remove', target: 'Mario');

        $this->assertSame(SemanticMutationType::RemoveParticipant, $mutation->semanticType());
    }

    public function test_targeted_participant_replace_is_replace_participant(): void
    {
        $mutation = new NormalizedMutation(field: 'participants', label: 'Participants', value: 'Marco', operation: 'replace', target: 'Luca');

        $this->assertSame(SemanticMutationType::ReplaceParticipant, $mutation->semanticType());
    }

    public function test_untargeted_participant_replace_is_not_replace_participant(): void
    {
        // A participants replace with no target is not an item swap; it must not
        // be classified as replace_participant.
        $mutation = new NormalizedMutation(field: 'participants', label: 'Participants', value: 'Marco', operation: 'replace');

        $this->assertSame(SemanticMutationType::Unknown, $mutation->semanticType());
    }

    public function test_default_stored_semantic_type_is_null_for_backward_compatibility(): void
    {
        // The stored property defaults to null; semanticType() derives on demand.
        $mutation = new NormalizedMutation(field: 'time', label: 'Time', value: '10:30');

        $this->assertNull($mutation->semanticType);
        $this->assertSame(SemanticMutationType::ReplaceTime, $mutation->semanticType());
    }
}
