<?php

namespace Tests\Feature\Actions;

use Fluxio\Actions\Contracts\RefinementInterpreterInterface;
use Fluxio\Actions\DTO\NormalizedMutation;
use Fluxio\Actions\DTO\SemanticRefinementMutation;
use Fluxio\Actions\Enums\SemanticMutationType;
use Fluxio\Actions\Support\SemanticRefinementLowerer;
use Tests\TestCase;

/**
 * Phase 8D.2: the arrow is flipped. The rule-based interpreter now EMITS Semantic
 * Refinement IR for the migrated mutation families, and lowering produces the
 * structural NormalizedMutation the runtime applies. These tests pin that the
 * interpreter emits the expected semantic operation (type + selector payload) and
 * that lowering yields the expected structural shape carrying the semantic type.
 *
 * Contained: it exercises interpret() + the lowerer directly, the same two steps
 * the live refinement seam runs.
 */
class SemanticRefinementParityTest extends TestCase
{
    private SemanticRefinementLowerer $lowerer;

    private RefinementInterpreterInterface $interpreter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lowerer = $this->app->make(SemanticRefinementLowerer::class);
        $this->interpreter = $this->app->make(RefinementInterpreterInterface::class);
    }

    private function onlySemantic(string $text): SemanticRefinementMutation
    {
        $operations = $this->interpreter->interpret($text);
        $this->assertCount(1, $operations, "Expected exactly one operation for [{$text}].");
        $this->assertInstanceOf(
            SemanticRefinementMutation::class,
            $operations[0],
            "Migrated mutation [{$text}] must now be emitted as Semantic Refinement IR.",
        );

        return $operations[0];
    }

    public function test_shift_time_is_emitted_as_semantic_ir_and_lowers_to_metadata_only_mutation(): void
    {
        $semantic = $this->onlySemantic('Push it by 30 minutes');

        $this->assertSame(SemanticMutationType::ShiftTime, $semantic->type);
        $this->assertSame(30, $semantic->payload['amount']);
        $this->assertSame('minutes', $semantic->payload['unit']);
        $this->assertSame('later', $semantic->payload['direction']);

        $lowered = $this->lowerer->lower($semantic);
        $this->assertInstanceOf(NormalizedMutation::class, $lowered);
        $this->assertSame('time', $lowered->field);
        $this->assertSame('replace', $lowered->operation);
        $this->assertNull($lowered->value); // computed later by the service
        $this->assertSame('temporal_shift', $lowered->metadata['contextual_operation']);
        $this->assertSame(30, $lowered->metadata['amount']);
        $this->assertSame('later', $lowered->metadata['direction']);
        $this->assertSame(SemanticMutationType::ShiftTime, $lowered->semanticType());
    }

    public function test_shift_time_hours_extracted_as_hours_and_normalized_by_lowering(): void
    {
        $semantic = $this->onlySemantic('Move it one hour earlier');

        // The interpreter extracts the surface unit; the lowerer normalizes to minutes.
        $this->assertSame(1, $semantic->payload['amount']);
        $this->assertSame('hours', $semantic->payload['unit']);

        $lowered = $this->lowerer->lower($semantic);
        $this->assertSame(60, $lowered->metadata['amount']);
        $this->assertSame('earlier', $lowered->metadata['direction']);
    }

    public function test_replace_time_is_emitted_as_semantic_ir(): void
    {
        $semantic = $this->onlySemantic('At 11:00');

        $this->assertSame(SemanticMutationType::ReplaceTime, $semantic->type);
        $this->assertSame('11:00', $semantic->payload['value']);

        $lowered = $this->lowerer->lower($semantic);
        $this->assertSame('time', $lowered->field);
        $this->assertSame('replace', $lowered->operation);
        $this->assertSame('11:00', $lowered->value);
        $this->assertSame(SemanticMutationType::ReplaceTime, $lowered->semanticType());
    }

    public function test_replace_date_is_emitted_as_semantic_ir(): void
    {
        $semantic = $this->onlySemantic('Move it to Friday');

        $this->assertSame(SemanticMutationType::ReplaceDate, $semantic->type);

        $lowered = $this->lowerer->lower($semantic);
        $this->assertSame('date', $lowered->field);
        $this->assertSame('replace', $lowered->operation);
        $this->assertSame($semantic->payload['value'], $lowered->value);
        $this->assertSame(SemanticMutationType::ReplaceDate, $lowered->semanticType());
    }

    public function test_add_participant_is_emitted_as_semantic_ir(): void
    {
        $semantic = $this->onlySemantic('Add Marco too');

        $this->assertSame(SemanticMutationType::AddParticipant, $semantic->type);
        $this->assertSame('Marco', $semantic->payload['value']);

        $lowered = $this->lowerer->lower($semantic);
        $this->assertSame('participants', $lowered->field);
        $this->assertSame('append', $lowered->operation);
        $this->assertSame('Marco', $lowered->value);
        $this->assertNull($lowered->target);
        $this->assertSame(SemanticMutationType::AddParticipant, $lowered->semanticType());
    }

    public function test_replace_participant_is_emitted_as_semantic_ir(): void
    {
        $semantic = $this->onlySemantic('Replace Marco with Mario');

        $this->assertSame(SemanticMutationType::ReplaceParticipant, $semantic->type);
        $this->assertSame('Marco', $semantic->target);
        $this->assertSame('Mario', $semantic->payload['value']);

        $lowered = $this->lowerer->lower($semantic);
        $this->assertSame('participants', $lowered->field);
        $this->assertSame('replace', $lowered->operation);
        $this->assertSame('Mario', $lowered->value);
        $this->assertSame('Marco', $lowered->target);
        $this->assertSame(SemanticMutationType::ReplaceParticipant, $lowered->semanticType());
    }

    public function test_remove_participant_is_emitted_as_semantic_ir(): void
    {
        $semantic = $this->onlySemantic('Remove Mario');

        $this->assertSame(SemanticMutationType::RemoveParticipant, $semantic->type);
        $this->assertSame('Mario', $semantic->target);

        $lowered = $this->lowerer->lower($semantic);
        $this->assertSame('participants', $lowered->field);
        $this->assertSame('remove', $lowered->operation);
        $this->assertNull($lowered->value);
        $this->assertSame('Mario', $lowered->target);
        $this->assertSame(SemanticMutationType::RemoveParticipant, $lowered->semanticType());
    }

    public function test_priority_remains_structural_not_yet_migrated(): void
    {
        // Priority replace is not part of Phase 8D.2; the interpreter still emits it
        // as a structural NormalizedMutation that passes through the seam unchanged.
        $operations = $this->interpreter->interpret('High priority');

        $this->assertCount(1, $operations);
        $this->assertInstanceOf(NormalizedMutation::class, $operations[0]);
        $this->assertSame('priority', $operations[0]->field);
        $this->assertSame('replace', $operations[0]->operation);
        $this->assertSame('high', $operations[0]->value);
    }
}
