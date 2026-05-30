<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Actions\Support\ProposalRuntimeContextFactory;
use Tests\TestCase;

/**
 * Phase 7A: ProposalRuntimeContext is a deterministic, read-only snapshot
 * derived from the current ActionProposal. These tests cover the factory
 * derivation (temporal / collections / blocking ambiguities) and the DTO
 * helper methods. The context is not chat memory and is not persisted.
 *
 * The proposal here is built in memory (newModelInstance) — no database is
 * touched, mirroring that the factory performs no queries.
 */
class ProposalRuntimeContextTest extends TestCase
{
    private function makeProposal(): ActionProposal
    {
        $proposal = (new ActionProposal)->newInstance([], true);
        $proposal->id = 'ctx-proposal-1';
        $proposal->intent = 'schedule_meeting';
        $proposal->status = 'draft';
        $proposal->confidence = 0.7;
        $proposal->source_text = 'Schedule a meeting with Rossi tomorrow afternoon';
        $proposal->entities = ['lead_query' => 'Rossi', 'date' => '2026-05-31', 'time' => '15:00'];
        $proposal->missing = [
            ['key' => 'location', 'label' => 'Location', 'required' => true],
        ];
        $proposal->warnings = ["Time inferred from 'afternoon' as 15:00."];
        $proposal->editable_fields = [
            ['key' => 'date', 'label' => 'Date', 'value' => '2026-05-31', 'source' => 'detected', 'required' => true],
            ['key' => 'time', 'label' => 'Time', 'value' => '15:00', 'source' => 'detected', 'required' => true],
            ['key' => 'participants', 'label' => 'Participants', 'value' => ['Mario', 'Luca'], 'source' => 'detected', 'required' => false],
            ['key' => 'location', 'label' => 'Location', 'value' => null, 'source' => 'missing', 'required' => true],
        ];
        $proposal->ambiguities = [
            [
                'key' => 'lead', 'label' => 'Lead', 'reason' => 'multiple_matches', 'blocking' => true,
                'query' => 'Rossi', 'selected_candidate_id' => null,
                'candidates' => [['id' => 1, 'label' => 'Mario Rossi', 'type' => 'person']],
            ],
            [
                'key' => 'other', 'label' => 'Other', 'reason' => 'soft', 'blocking' => false,
                'selected_candidate_id' => null, 'candidates' => [],
            ],
        ];
        $proposal->last_refinement = [
            'text' => 'tomorrow afternoon', 'effective_text' => 'tomorrow afternoon',
            'summary' => 'Date and time added.', 'changes' => [],
        ];

        return $proposal;
    }

    private function context(): \Fluxio\Actions\DTO\ProposalRuntimeContext
    {
        return (new ProposalRuntimeContextFactory)->fromProposal($this->makeProposal());
    }

    public function test_factory_carries_core_proposal_scoped_fields(): void
    {
        $ctx = $this->context();

        $this->assertSame('ctx-proposal-1', $ctx->proposalId);
        $this->assertSame('schedule_meeting', $ctx->intent);
        $this->assertSame('draft', $ctx->status);
        $this->assertSame(['lead_query' => 'Rossi', 'date' => '2026-05-31', 'time' => '15:00'], $ctx->entities);
        $this->assertContains("Time inferred from 'afternoon' as 15:00.", $ctx->warnings);
        $this->assertIsArray($ctx->lastRefinement);
        $this->assertSame('Date and time added.', $ctx->lastRefinement['summary']);
    }

    public function test_factory_derives_temporal_values(): void
    {
        $ctx = $this->context();

        $this->assertSame(['date' => '2026-05-31', 'time' => '15:00'], $ctx->temporal);
        $this->assertSame('2026-05-31', $ctx->temporalValue('date'));
        $this->assertSame('15:00', $ctx->temporalValue('time'));
        $this->assertNull($ctx->temporalValue('weekday'));
    }

    public function test_factory_derives_collections(): void
    {
        $ctx = $this->context();

        $this->assertSame(['Mario', 'Luca'], $ctx->collection('participants'));
        $this->assertSame([], $ctx->collection('attachments'));
        // Scalar fields are not collections.
        $this->assertArrayNotHasKey('date', $ctx->collections);
    }

    public function test_factory_derives_blocking_ambiguities_only(): void
    {
        $ctx = $this->context();

        $this->assertCount(1, $ctx->blockingAmbiguities);
        $this->assertSame('lead', $ctx->blockingAmbiguities[0]['key']);
        $this->assertTrue($ctx->hasBlockingAmbiguities());
    }

    public function test_dto_field_helpers(): void
    {
        $ctx = $this->context();

        $this->assertTrue($ctx->hasField('date'));
        $this->assertFalse($ctx->hasField('nonexistent'));
        $this->assertSame('15:00', $ctx->fieldValue('time'));
        $this->assertNull($ctx->fieldValue('location'));   // present but null value
        $this->assertNull($ctx->fieldValue('nonexistent'));
    }

    public function test_dto_missing_keys(): void
    {
        $ctx = $this->context();

        $this->assertSame(['location'], $ctx->missingKeys());
    }

    public function test_empty_proposal_yields_empty_derivations(): void
    {
        $proposal = (new ActionProposal)->newInstance([], true);
        $proposal->id = 'empty-1';
        $proposal->intent = 'create_task';
        $proposal->status = 'ready';
        // entities/editable_fields/etc. left null to exercise the null-coalescing paths.

        $ctx = (new ProposalRuntimeContextFactory)->fromProposal($proposal);

        $this->assertSame([], $ctx->temporal);
        $this->assertSame([], $ctx->collections);
        $this->assertSame([], $ctx->blockingAmbiguities);
        $this->assertSame([], $ctx->missingKeys());
        $this->assertFalse($ctx->hasBlockingAmbiguities());
        $this->assertNull($ctx->lastRefinement);
    }
}
