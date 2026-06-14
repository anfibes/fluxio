<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Leads\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExecuteAssignLeadProposalTest extends TestCase
{
    use RefreshDatabase;

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function actingAsUser(?string $name = null): User
    {
        $user = User::factory()->create($name !== null ? ['name' => $name] : []);
        Sanctum::actingAs($user);

        return $user;
    }

    private function confirmedAssignProposal(User $owner, string $leadLabel, string $assigneeLabel): ActionProposal
    {
        return ActionProposal::create([
            'user_id'     => $owner->id,
            'intent'      => 'assign_lead',
            'status'      => 'confirmed',
            'confidence'  => 0.8,
            'source_text' => "Assign {$leadLabel} to {$assigneeLabel}",
            'entities'    => ['lead' => $leadLabel, 'assignee' => $assigneeLabel],
            'missing'     => [],
            'warnings'    => [],
            'editable_fields' => [
                ['key' => 'lead',     'label' => 'Lead',     'value' => $leadLabel,     'source' => 'detected', 'required' => true],
                ['key' => 'assignee', 'label' => 'Assignee', 'value' => $assigneeLabel, 'source' => 'detected', 'required' => true],
            ],
            'changes' => [
                ['type' => 'assign', 'label' => 'Assign Lead', 'module' => 'leads', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [],
        ]);
    }

    // ── Happy path ────────────────────────────────────────────────────────────

    public function test_confirmed_assign_lead_persists_assignment(): void
    {
        $lead = Lead::factory()->create(['name' => 'Acme Corp', 'company' => null]);
        $assignee = User::factory()->create(['name' => 'Laura Bianchi']);
        $actor = $this->actingAsUser();

        $proposal = $this->confirmedAssignProposal($actor, 'Acme Corp', 'Laura Bianchi');

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('leads', [
            'id'                  => $lead->id,
            'assigned_to_user_id' => $assignee->id,
        ]);

        $refreshed = $lead->fresh();
        $this->assertNotNull($refreshed->assigned_at);
    }

    public function test_execution_result_contains_lead_and_user_ids(): void
    {
        $lead     = Lead::factory()->create(['name' => 'BetaCo', 'company' => null]);
        $assignee = User::factory()->create(['name' => 'Giorgio Russo']);
        $actor    = $this->actingAsUser();

        $proposal = $this->confirmedAssignProposal($actor, 'BetaCo', 'Giorgio Russo');

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $response->assertStatus(200);

        $details = $response->json('data.execution_result.details');
        $this->assertEquals('lead_assigned', $details['type']);
        $this->assertEquals('assigned', $details['status']);
        $this->assertEquals('BetaCo', $details['lead']);
        $this->assertEquals($lead->id, $details['lead_id']);
        $this->assertEquals('Giorgio Russo', $details['assignee']);
        $this->assertEquals($assignee->id, $details['user_id']);
    }

    public function test_lead_matched_by_company_field(): void
    {
        $lead     = Lead::factory()->create(['name' => 'Giovanni Verdi', 'company' => 'VerdiCo']);
        $assignee = User::factory()->create(['name' => 'Sara Neri']);
        $actor    = $this->actingAsUser();

        // Resolve lead by company name, not personal name
        $proposal = $this->confirmedAssignProposal($actor, 'VerdiCo', 'Sara Neri');

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $response->assertStatus(200)->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('leads', [
            'id'                  => $lead->id,
            'assigned_to_user_id' => $assignee->id,
        ]);
    }

    // ── Lifecycle guard: confirmation required ────────────────────────────────

    public function test_assign_lead_does_not_execute_before_confirmation(): void
    {
        $actor = $this->actingAsUser();

        $proposal = ActionProposal::create([
            'user_id'     => $actor->id,
            'intent'      => 'assign_lead',
            'status'      => 'ready',
            'confidence'  => 0.8,
            'source_text' => 'Assign Rossini to Marco',
            'entities'    => ['lead' => 'Rossini', 'assignee' => 'Marco'],
            'missing'     => [],
            'warnings'    => [],
            'editable_fields' => [],
            'changes'     => [],
            'needs_confirmation' => true,
            'ambiguities' => [],
        ]);

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['proposal']]);

        // No assignment was written
        $this->assertDatabaseMissing('leads', ['assigned_to_user_id' => $actor->id]);
    }

    // ── Ambiguous lead blocks execution ───────────────────────────────────────

    public function test_ambiguous_lead_blocks_execution_and_proposal_stays_confirmed(): void
    {
        Lead::factory()->create(['name' => 'Rossi', 'company' => null]);
        Lead::factory()->create(['name' => 'Rossi', 'company' => null]);

        $assignee = User::factory()->create(['name' => 'Marco']);
        $actor    = $this->actingAsUser();

        $proposal = $this->confirmedAssignProposal($actor, 'Rossi', 'Marco');

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['lead']]);

        // Proposal stays confirmed (validation error, not execution failure)
        $this->assertDatabaseHas('action_proposals', [
            'id'     => $proposal->id,
            'status' => 'confirmed',
        ]);

        $refreshed = ActionProposal::find($proposal->id);
        $this->assertNull($refreshed->failed_at);
        $this->assertNull($refreshed->failure_reason_code);

        // No lead was assigned
        $this->assertDatabaseMissing('leads', ['assigned_to_user_id' => $assignee->id]);
    }

    // ── Unknown lead fails safely ─────────────────────────────────────────────

    public function test_unknown_lead_blocks_execution_safely(): void
    {
        $assignee = User::factory()->create(['name' => 'Marco']);
        $actor    = $this->actingAsUser();

        // No lead named 'GhostCo' exists
        $proposal = $this->confirmedAssignProposal($actor, 'GhostCo', 'Marco');

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['lead']]);

        // Proposal stays confirmed (validation error, not execution failure)
        $refreshed = ActionProposal::find($proposal->id);
        $this->assertEquals('confirmed', $refreshed->status);
        $this->assertNull($refreshed->failed_at);
    }

    // ── Unknown assignee does not silently assign ─────────────────────────────

    public function test_unknown_assignee_does_not_silently_assign(): void
    {
        $lead  = Lead::factory()->create(['name' => 'Rossini', 'company' => null]);
        $actor = $this->actingAsUser();

        // No user named 'GhostUser' exists
        $proposal = $this->confirmedAssignProposal($actor, 'Rossini', 'GhostUser');

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['assignee']]);

        // Lead must remain unassigned
        $this->assertDatabaseHas('leads', [
            'id'                  => $lead->id,
            'assigned_to_user_id' => null,
        ]);

        // Proposal stays confirmed, no failure state
        $refreshed = ActionProposal::find($proposal->id);
        $this->assertEquals('confirmed', $refreshed->status);
        $this->assertNull($refreshed->failed_at);
    }

    // ── Ambiguous assignee blocks execution ───────────────────────────────────

    public function test_ambiguous_assignee_blocks_execution(): void
    {
        $lead = Lead::factory()->create(['name' => 'Rossini', 'company' => null]);
        User::factory()->create(['name' => 'Marco Rossi']);
        User::factory()->create(['name' => 'Marco Rossi']);
        $actor = $this->actingAsUser();

        $proposal = $this->confirmedAssignProposal($actor, 'Rossini', 'Marco Rossi');

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['assignee']]);

        // Lead was not assigned
        $this->assertDatabaseHas('leads', [
            'id'                  => $lead->id,
            'assigned_to_user_id' => null,
        ]);
    }

    // ── Full lifecycle: interpret → confirm → execute ─────────────────────────

    public function test_full_lifecycle_assigns_lead_in_database(): void
    {
        $lead     = Lead::factory()->create(['name' => 'Rossini', 'company' => null]);
        $assignee = User::factory()->create(['name' => 'Marco']);
        $actor    = $this->actingAsUser();

        // Interpret
        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Assign Rossini to Marco']);
        $r1->assertStatus(200)->assertJsonPath('data.intent', 'assign_lead');
        $proposalId = $r1->json('data.id');

        // Confirm
        $r2 = $this->postJson("/api/actions/{$proposalId}/confirm");
        $r2->assertStatus(200)->assertJsonPath('data.status', 'confirmed');

        // Execute
        $r3 = $this->postJson("/api/actions/{$proposalId}/execute");
        $r3->assertStatus(200)->assertJsonPath('data.status', 'executed');

        // Real side effect
        $this->assertDatabaseHas('leads', [
            'id'                  => $lead->id,
            'assigned_to_user_id' => $assignee->id,
        ]);
    }

    // ── create_task is unaffected ─────────────────────────────────────────────

    public function test_create_task_behavior_unchanged_after_assign_lead_changes(): void
    {
        $actor = $this->actingAsUser();

        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Create a task for Rossini']);
        $r1->assertStatus(200)->assertJsonPath('data.intent', 'create_task');

        $this->postJson("/api/actions/{$r1->json('data.id')}/confirm")->assertStatus(200);

        $r3 = $this->postJson("/api/actions/{$r1->json('data.id')}/execute");
        $r3->assertStatus(200)->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseCount('tasks', 1);
        $this->assertEquals('Task created successfully.', $r3->json('data.execution_result.summary'));
    }
}
