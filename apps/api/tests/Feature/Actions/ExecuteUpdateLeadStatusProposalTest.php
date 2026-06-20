<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Leads\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExecuteUpdateLeadStatusProposalTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function confirmedProposal(User $owner, string $leadLabel, string $state): ActionProposal
    {
        return ActionProposal::create([
            'user_id' => $owner->id,
            'intent' => 'update_lead_status',
            'status' => 'confirmed',
            'confidence' => 0.85,
            'source_text' => "Mark {$leadLabel} as {$state}",
            'entities' => ['lead' => $leadLabel, 'state' => $state],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [
                ['key' => 'lead',  'label' => 'Lead',   'value' => $leadLabel, 'source' => 'detected', 'required' => true],
                ['key' => 'state', 'label' => 'Status', 'value' => $state,     'source' => 'detected', 'required' => true],
            ],
            'changes' => [
                ['type' => 'update', 'label' => 'Update Lead Status', 'module' => 'leads', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [],
        ]);
    }

    // ── Happy path ────────────────────────────────────────────────────────────

    public function test_confirmed_update_persists_new_status(): void
    {
        $lead = Lead::factory()->create(['name' => 'Rossini', 'company' => null, 'status' => 'new']);
        $actor = $this->actingAsUser();

        $proposal = $this->confirmedProposal($actor, 'Rossini', 'contacted');

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'contacted']);
    }

    public function test_execution_result_contains_safe_lead_and_status(): void
    {
        $lead = Lead::factory()->create(['name' => 'BetaCo', 'company' => null, 'status' => 'new']);
        $actor = $this->actingAsUser();

        $proposal = $this->confirmedProposal($actor, 'BetaCo', 'qualified');

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $response->assertStatus(200);

        $details = $response->json('data.execution_result.details');
        $this->assertEquals('lead_status_updated', $details['type']);
        $this->assertEquals('qualified', $details['status']);
        $this->assertEquals('BetaCo', $details['lead']);
        $this->assertEquals($lead->id, $details['lead_id']);
    }

    public function test_lead_matched_by_company_field(): void
    {
        $lead = Lead::factory()->create(['name' => 'Giovanni Verdi', 'company' => 'VerdiCo', 'status' => 'new']);
        $actor = $this->actingAsUser();

        $proposal = $this->confirmedProposal($actor, 'VerdiCo', 'won');

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'won']);
    }

    // ── Lifecycle guard: confirmation required ────────────────────────────────

    public function test_does_not_execute_before_confirmation(): void
    {
        $lead = Lead::factory()->create(['name' => 'Rossini', 'company' => null, 'status' => 'new']);
        $actor = $this->actingAsUser();

        $proposal = $this->confirmedProposal($actor, 'Rossini', 'contacted');
        $proposal->update(['status' => 'ready']);

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['proposal']]);

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'new']);
    }

    // ── Unknown / ambiguous lead fail safely (validation, not failure) ────────

    public function test_unknown_lead_blocks_execution_safely(): void
    {
        $actor = $this->actingAsUser();

        $proposal = $this->confirmedProposal($actor, 'GhostCo', 'contacted');

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['lead']]);

        $refreshed = ActionProposal::find($proposal->id);
        $this->assertEquals('confirmed', $refreshed->status);
        $this->assertNull($refreshed->failed_at);
    }

    public function test_ambiguous_lead_blocks_execution_and_proposal_stays_confirmed(): void
    {
        Lead::factory()->create(['name' => 'Rossi', 'company' => null, 'status' => 'new']);
        Lead::factory()->create(['name' => 'Rossi', 'company' => null, 'status' => 'new']);
        $actor = $this->actingAsUser();

        $proposal = $this->confirmedProposal($actor, 'Rossi', 'contacted');

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['lead']]);

        $this->assertDatabaseMissing('leads', ['status' => 'contacted']);

        $refreshed = ActionProposal::find($proposal->id);
        $this->assertEquals('confirmed', $refreshed->status);
        $this->assertNull($refreshed->failed_at);
    }

    public function test_invalid_status_blocks_execution_safely(): void
    {
        $lead = Lead::factory()->create(['name' => 'Rossini', 'company' => null, 'status' => 'new']);
        $actor = $this->actingAsUser();

        // A status not in Lead::STATUSES must never be persisted.
        $proposal = $this->confirmedProposal($actor, 'Rossini', 'archived');

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['status']]);

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'new']);

        $refreshed = ActionProposal::find($proposal->id);
        $this->assertEquals('confirmed', $refreshed->status);
    }

    // ── Full lifecycle: interpret → confirm → execute ─────────────────────────

    public function test_full_lifecycle_updates_lead_status_in_database(): void
    {
        // Fixture 'Rossini' resolves the proposal; the DB Lead is what the executor writes.
        $lead = Lead::factory()->create(['name' => 'Rossini', 'company' => null, 'status' => 'new']);
        $this->actingAsUser();

        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Mark Rossini as contacted']);
        $r1->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_lead_status')
            ->assertJsonPath('data.status', 'ready');
        $proposalId = $r1->json('data.id');

        $this->postJson("/api/actions/{$proposalId}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $this->postJson("/api/actions/{$proposalId}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'contacted']);
    }

    // ── assign_lead / update_task_status remain unaffected ────────────────────

    public function test_assign_lead_execution_unchanged(): void
    {
        $lead = Lead::factory()->create(['name' => 'Rossini', 'company' => null]);
        $assignee = User::factory()->create(['name' => 'Marco']);
        $actor = $this->actingAsUser();

        $proposal = ActionProposal::create([
            'user_id' => $actor->id,
            'intent' => 'assign_lead',
            'status' => 'confirmed',
            'confidence' => 0.8,
            'source_text' => 'Assign Rossini to Marco',
            'entities' => ['lead' => 'Rossini', 'assignee' => 'Marco'],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [
                ['key' => 'lead',     'label' => 'Lead',     'value' => 'Rossini', 'source' => 'detected', 'required' => true],
                ['key' => 'assignee', 'label' => 'Assignee', 'value' => 'Marco',   'source' => 'detected', 'required' => true],
            ],
            'changes' => [
                ['type' => 'assign', 'label' => 'Assign Lead', 'module' => 'leads', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [],
        ]);

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $response->assertStatus(200)->assertJsonPath('data.status', 'executed');
        $this->assertEquals('lead_assigned', $response->json('data.execution_result.details.type'));
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'assigned_to_user_id' => $assignee->id]);
        // assign_lead does not touch status
        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'new']);
    }

    // ── Invariant regression: resolution agrees with execution ────────────────

    /**
     * The core bug this patch fixes: a proposal made ready/resolved through entity
     * resolution must be executable against the SAME real Lead row. Here an ambiguous
     * "Rossi" is narrowed by ordinal ("the second one") to a real candidate, then
     * confirmed and executed — and exactly that Lead row is updated.
     */
    public function test_resolve_rossi_pick_second_then_execute_updates_that_lead(): void
    {
        // Real ambiguous leads — the rows the resolver scores AND the executor acts on.
        Lead::factory()->create(['name' => 'Rossi SRL', 'company' => 'Rossi SRL', 'status' => 'new']);
        Lead::factory()->create(['name' => 'Mario Rossi', 'company' => null, 'status' => 'new']);
        Lead::factory()->create(['name' => 'Studio Rossi', 'company' => 'Studio Rossi', 'status' => 'new']);
        $this->actingAsUser();

        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Mark Rossi as qualified']);
        $r1->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_lead_status')
            ->assertJsonPath('data.status', 'draft');

        $candidates = $r1->json('data.ambiguities.0.candidates');
        $this->assertGreaterThanOrEqual(2, count($candidates));
        $secondLabel = $candidates[1]['label'];
        $secondId = $candidates[1]['id'];
        // Candidate ids are real Lead primary keys.
        $this->assertEquals($secondLabel, Lead::find($secondId)?->name);

        $proposalId = $r1->json('data.id');

        $r2 = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'the second one']);
        $r2->assertStatus(200)->assertJsonPath('data.status', 'ready');
        $fields = collect($r2->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals($secondLabel, $fields['lead']['value']);

        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);
        $this->postJson("/api/actions/{$proposalId}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        // The exact resolved row is updated — no proposal/execution disagreement.
        $this->assertDatabaseHas('leads', ['id' => $secondId, 'status' => 'qualified']);
    }

    /**
     * Regression: with an empty leads table, the (now removed) in-memory fixtures must
     * not appear. "Rossi" resolves to nothing — no candidates, no ready proposal.
     */
    public function test_empty_lead_database_produces_no_hardcoded_resolution(): void
    {
        $this->actingAsUser();

        $r = $this->postJson('/api/actions/interpret', ['text' => 'Mark Rossi as qualified']);

        $r->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_lead_status')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.ambiguities', []);

        $missing = collect($r->json('data.missing'))->pluck('key')->all();
        $this->assertContains('lead', $missing);
    }
}
