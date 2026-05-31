<?php

namespace Tests\Feature\Actions;

use Fluxio\Actions\Contracts\RefinementInterpreterInterface;
use Fluxio\Actions\DTO\NormalizedMutation;
use Fluxio\Actions\DTO\SemanticRefinementMutation;
use Fluxio\Actions\Enums\SemanticMutationType;
use Fluxio\Actions\Support\SemanticRefinementLowerer;
use Tests\TestCase;

/**
 * Phase 8B: prove the Semantic Refinement IR is a faithful representation of
 * what the current rule-based interpreter already produces. For equivalent
 * inputs, lowering a SemanticRefinementMutation yields a structural
 * NormalizedMutation equal (in the fields the application boundary consumes) to
 * the one the interpreter emits today.
 *
 * This is a contained equivalence proof: it wires nothing into the live
 * refinement endpoint and changes no runtime behavior.
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

    private function onlyMutation(string $text): NormalizedMutation
    {
        $mutations = $this->interpreter->interpret($text);
        $this->assertCount(1, $mutations, "Expected exactly one mutation for [{$text}].");

        return $mutations[0];
    }

    private function assertStructurallyEqual(NormalizedMutation $a, NormalizedMutation $b): void
    {
        $this->assertSame($a->field, $b->field, 'field');
        $this->assertSame($a->operation, $b->operation, 'operation');
        $this->assertSame($a->value, $b->value, 'value');
        $this->assertSame($a->target, $b->target, 'target');
        $this->assertSame($a->semanticType(), $b->semanticType(), 'semanticType');
        $this->assertSame(
            $a->metadata['contextual_operation'] ?? null,
            $b->metadata['contextual_operation'] ?? null,
            'contextual_operation',
        );
    }

    public function test_shift_time_lowering_matches_interpreter_output(): void
    {
        $fromText = $this->onlyMutation('Push it by 30 minutes');

        $lowered = $this->lowerer->lower(new SemanticRefinementMutation(
            type: SemanticMutationType::ShiftTime,
            payload: ['amount' => 30, 'unit' => 'minutes', 'direction' => 'later'],
            source: 'inferred',
        ));

        $this->assertStructurallyEqual($fromText, $lowered);
        $this->assertSame($fromText->metadata['amount'], $lowered->metadata['amount']);
        $this->assertSame($fromText->metadata['direction'], $lowered->metadata['direction']);
    }

    public function test_replace_time_lowering_matches_interpreter_output(): void
    {
        $fromText = $this->onlyMutation('At 11:00');

        $lowered = $this->lowerer->lower(new SemanticRefinementMutation(
            type: SemanticMutationType::ReplaceTime,
            payload: ['value' => $fromText->value],
        ));

        $this->assertStructurallyEqual($fromText, $lowered);
    }

    public function test_replace_date_lowering_matches_interpreter_output(): void
    {
        $fromText = $this->onlyMutation('Move it to Friday');

        $lowered = $this->lowerer->lower(new SemanticRefinementMutation(
            type: SemanticMutationType::ReplaceDate,
            payload: ['value' => $fromText->value],
        ));

        $this->assertStructurallyEqual($fromText, $lowered);
    }

    public function test_add_participant_lowering_matches_interpreter_output(): void
    {
        $fromText = $this->onlyMutation('Add Marco too');

        $lowered = $this->lowerer->lower(new SemanticRefinementMutation(
            type: SemanticMutationType::AddParticipant,
            payload: ['value' => $fromText->value],
        ));

        $this->assertStructurallyEqual($fromText, $lowered);
    }

    public function test_replace_participant_lowering_matches_interpreter_output(): void
    {
        $fromText = $this->onlyMutation('Replace Marco with Mario');

        $lowered = $this->lowerer->lower(new SemanticRefinementMutation(
            type: SemanticMutationType::ReplaceParticipant,
            payload: ['value' => $fromText->value],
            target: $fromText->target,
        ));

        $this->assertStructurallyEqual($fromText, $lowered);
    }

    public function test_remove_participant_lowering_matches_interpreter_output(): void
    {
        $fromText = $this->onlyMutation('Remove Mario');

        $lowered = $this->lowerer->lower(new SemanticRefinementMutation(
            type: SemanticMutationType::RemoveParticipant,
            target: $fromText->target,
        ));

        $this->assertStructurallyEqual($fromText, $lowered);
    }
}
