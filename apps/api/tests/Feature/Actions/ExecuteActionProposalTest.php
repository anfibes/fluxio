<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Leads\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExecuteActionProposalTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function interpretAndConfirm(string $text): array
    {
        $interpret = $this->postJson('/api/actions/interpret', ['text' => $text]);
        $interpret->assertStatus(200);

        $proposalId = $interpret->json('data.id');

        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);

        return ['id' => $proposalId];
    }

    // --- happy path ---

    public function test_confirmed_create_task_proposal_executes_and_creates_task(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');

        $response = $this->postJson("/api/actions/{$proposal['id']}/execute");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertNotNull($response->json('data.executed_at'));
        $this->assertEquals('Task created successfully.', $response->json('data.execution_result.summary'));
        $this->assertNotNull($response->json('data.execution_result.details.resource_id'));
        $this->assertEquals('tasks', $response->json('data.execution_result.details.module'));
        $this->assertEquals('created', $response->json('data.execution_result.details.action'));

        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_execute_returns_executed_message(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');

        $this->postJson("/api/actions/{$proposal['id']}/execute")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Action executed successfully.');
    }

    // --- idempotency ---

    public function test_execute_is_idempotent(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');

        $first = $this->postJson("/api/actions/{$proposal['id']}/execute");
        $firstResult = $first->json('data.execution_result');

        $second = $this->postJson("/api/actions/{$proposal['id']}/execute");
        $secondResult = $second->json('data.execution_result');

        $second->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertEquals($firstResult, $secondResult);
        $this->assertDatabaseCount('tasks', 1);
    }

    // --- lifecycle guards ---

    public function test_draft_proposal_cannot_be_executed(): void
    {
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', [
            'text' => 'Schedule a call with Rossini',
        ]);
        $interpret->assertStatus(200);

        $this->postJson("/api/actions/{$interpret->json('data.id')}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['proposal']]);
    }

    public function test_ready_proposal_cannot_be_executed(): void
    {
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', [
            'text' => 'Create a task for Rossini',
        ]);
        $interpret->assertStatus(200);
        $this->assertEquals('ready', $interpret->json('data.status'));

        $this->postJson("/api/actions/{$interpret->json('data.id')}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['proposal']]);
    }

    // --- ambiguous lead ---

    public function test_ambiguous_lead_returns_422_and_does_not_create_task(): void
    {
        $this->actingAsUser();

        Lead::factory()->create(['name' => 'Rossini']);
        Lead::factory()->create(['name' => 'Rossini']);

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');

        $response = $this->postJson("/api/actions/{$proposal['id']}/execute");

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['lead']]);

        $this->assertDatabaseCount('tasks', 0);

        $this->assertDatabaseHas('action_proposals', [
            'id' => $proposal['id'],
            'status' => 'confirmed',
        ]);
    }

    // --- zero leads ---

    public function test_no_matching_lead_creates_task_without_lead_id(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');

        $this->postJson("/api/actions/{$proposal['id']}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseHas('tasks', ['lead_id' => null]);
    }

    // --- ownership ---

    public function test_user_cannot_execute_another_users_proposal(): void
    {
        $userA = User::factory()->create();
        Sanctum::actingAs($userA);

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');
        $proposalId = $proposal['id'];

        $userB = User::factory()->create();
        Sanctum::actingAs($userB);

        $this->postJson("/api/actions/{$proposalId}/execute")
            ->assertStatus(404);
    }

    // --- lead resolution ---

    public function test_single_matching_lead_is_linked_to_created_task(): void
    {
        $this->actingAsUser();

        $lead = Lead::factory()->create(['name' => 'Rossini']);

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');

        $this->postJson("/api/actions/{$proposal['id']}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('tasks', ['lead_id' => $lead->id]);
    }

    // --- terminal status guard ---

    public function test_failed_proposal_cannot_be_executed(): void
    {
        $user = $this->actingAsUser();

        $proposal = ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'create_task',
            'status' => 'failed',
            'confidence' => 0.9,
            'source_text' => 'Create a task for Rossini',
            'entities' => ['lead' => 'Rossini'],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [],
            'changes' => [],
            'needs_confirmation' => true,
        ]);

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['proposal']]);

        $this->assertDatabaseHas('action_proposals', [
            'id' => $proposal->id,
            'status' => 'failed',
        ]);
    }

    // --- unsupported intent ---

    public function test_unsupported_intent_marks_proposal_as_failed(): void
    {
        $user = $this->actingAsUser();

        $proposal = ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'unsupported_intent',
            'status' => 'confirmed',
            'confidence' => 0.5,
            'source_text' => 'Do something unusual',
            'entities' => [],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [],
            'changes' => [],
            'needs_confirmation' => true,
        ]);

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(500);

        $refreshed = $proposal->fresh();
        $this->assertEquals('failed', $refreshed->status);
        $this->assertNotNull($refreshed->failed_at);
        $this->assertNotNull($refreshed->failure_reason);

        // Failure is typed + sanitized: the raw exception message (which names the
        // unsupported intent) never reaches persisted proposal state.
        $this->assertEquals(
            __('actions::actions.execution_failure.unsupported_intent'),
            $refreshed->failure_reason,
        );
        $this->assertStringNotContainsString('unsupported_intent', $refreshed->failure_reason);
        $this->assertStringNotContainsString('executor', $refreshed->failure_reason);

        $this->assertDatabaseCount('tasks', 0);
    }

    // --- persistence ---

    public function test_execution_result_is_stored_in_database(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');

        $response = $this->postJson("/api/actions/{$proposal['id']}/execute");
        $response->assertStatus(200);

        $refreshed = ActionProposal::find($proposal['id']);
        $result = $refreshed->execution_result;

        $this->assertNotNull($result);
        $this->assertEquals('Task created successfully.', $result['summary']);
        $this->assertEquals('tasks', $result['details']['module']);
        $this->assertEquals('created', $result['details']['action']);
        $this->assertEquals('task', $result['details']['resource_type']);
        $this->assertNotNull($result['details']['resource_id']);
    }

    public function test_executed_at_is_stored_in_database(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');

        $this->postJson("/api/actions/{$proposal['id']}/execute")->assertStatus(200);

        $refreshed = ActionProposal::find($proposal['id']);
        $this->assertNotNull($refreshed->executed_at);
    }

    // --- not found / auth ---

    public function test_missing_proposal_returns_404(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/actions/00000000-0000-0000-0000-000000000000/execute')
            ->assertStatus(404);
    }

    public function test_unauthenticated_execute_returns_401(): void
    {
        $this->postJson('/api/actions/some-id/execute')
            ->assertStatus(401);
    }
}
