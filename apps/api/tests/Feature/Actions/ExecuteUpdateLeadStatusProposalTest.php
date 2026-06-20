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
}
