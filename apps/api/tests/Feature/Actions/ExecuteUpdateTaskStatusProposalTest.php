<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExecuteUpdateTaskStatusProposalTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * LEGACY FIXTURE (deliberate): no `resolved_entities` key → the column stays
     * null, so these tests exercise the pre-identity-continuity textual fallback
     * of UpdateTaskStatusActionExecutor. Contract-path coverage lives in
     * ExecuteUpdateTaskStatusIdentityContinuityTest.
     */
    private function confirmedProposal(User $owner, string $taskTitle, string $state): ActionProposal
    {
        return ActionProposal::create([
            'user_id' => $owner->id,
            'intent' => 'update_task_status',
            'status' => 'confirmed',
            'confidence' => 0.85,
            'source_text' => "Mark the {$taskTitle} task as {$state}",
            'entities' => ['task' => $taskTitle, 'state' => $state],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [
                ['key' => 'task',  'label' => 'Task',   'value' => $taskTitle, 'source' => 'detected', 'required' => true],
                ['key' => 'state', 'label' => 'Status', 'value' => $state,     'source' => 'detected', 'required' => true],
            ],
            'changes' => [
                ['type' => 'update', 'label' => 'Update Task Status', 'module' => 'tasks', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [],
        ]);
    }

    // ── Happy path ────────────────────────────────────────────────────────────

    public function test_confirmed_update_persists_new_status(): void
    {
        $task = Task::factory()->create(['title' => 'Follow-up Rossi', 'status' => 'pending']);
        $actor = $this->actingAsUser();

        $proposal = $this->confirmedProposal($actor, 'Follow-up Rossi', 'completed');

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'status' => 'completed',
        ]);
    }

    public function test_execution_result_contains_safe_task_and_status(): void
    {
        $task = Task::factory()->create(['title' => 'Follow-up Bianchi', 'status' => 'pending']);
        $actor = $this->actingAsUser();

        $proposal = $this->confirmedProposal($actor, 'Follow-up Bianchi', 'in_progress');

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $response->assertStatus(200);

        $details = $response->json('data.execution_result.details');
        $this->assertEquals('task_status_updated', $details['type']);
        $this->assertEquals('in_progress', $details['status']);
        $this->assertEquals('Follow-up Bianchi', $details['task']);
        $this->assertEquals($task->id, $details['task_id']);
    }

    // ── Lifecycle guard: confirmation required ────────────────────────────────

    public function test_does_not_execute_before_confirmation(): void
    {
        $task = Task::factory()->create(['title' => 'Follow-up', 'status' => 'pending']);
        $actor = $this->actingAsUser();

        $proposal = $this->confirmedProposal($actor, 'Follow-up', 'completed');
        $proposal->update(['status' => 'ready']);

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['proposal']]);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'pending']);
    }

    // ── Unknown / ambiguous task fail safely (validation, not failure) ────────

    public function test_unknown_task_blocks_execution_safely(): void
    {
        $actor = $this->actingAsUser();

        $proposal = $this->confirmedProposal($actor, 'GhostTask', 'completed');

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['task']]);

        $refreshed = ActionProposal::find($proposal->id);
        $this->assertEquals('confirmed', $refreshed->status);
        $this->assertNull($refreshed->failed_at);
    }

    public function test_ambiguous_task_blocks_execution_and_proposal_stays_confirmed(): void
    {
        Task::factory()->create(['title' => 'Follow-up', 'status' => 'pending']);
        Task::factory()->create(['title' => 'Follow-up', 'status' => 'pending']);
        $actor = $this->actingAsUser();

        $proposal = $this->confirmedProposal($actor, 'Follow-up', 'completed');

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['task']]);

        $this->assertDatabaseMissing('tasks', ['status' => 'completed']);

        $refreshed = ActionProposal::find($proposal->id);
        $this->assertEquals('confirmed', $refreshed->status);
        $this->assertNull($refreshed->failed_at);
    }

    public function test_invalid_status_blocks_execution_safely(): void
    {
        $task = Task::factory()->create(['title' => 'Follow-up', 'status' => 'pending']);
        $actor = $this->actingAsUser();

        // A status not in Task::STATUSES must never be persisted.
        $proposal = $this->confirmedProposal($actor, 'Follow-up', 'archived');

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['status']]);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'pending']);

        $refreshed = ActionProposal::find($proposal->id);
        $this->assertEquals('confirmed', $refreshed->status);
    }

    // ── Full lifecycle: interpret → confirm → execute ─────────────────────────

    public function test_full_lifecycle_updates_task_in_database(): void
    {
        $task = Task::factory()->create(['title' => 'Follow-up Rossi', 'status' => 'pending']);
        $this->actingAsUser();

        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Mark the Follow-up Rossi task as completed']);
        $r1->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_task_status')
            ->assertJsonPath('data.status', 'ready');
        $proposalId = $r1->json('data.id');

        $this->postJson("/api/actions/{$proposalId}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $this->postJson("/api/actions/{$proposalId}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'completed']);
    }

    // ── create_task / assign_lead remain unaffected ───────────────────────────

    public function test_create_task_behavior_unchanged(): void
    {
        $this->actingAsUser();

        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Create a task for Rossini']);
        $r1->assertStatus(200)->assertJsonPath('data.intent', 'create_task');

        $this->postJson("/api/actions/{$r1->json('data.id')}/confirm")->assertStatus(200);

        $r3 = $this->postJson("/api/actions/{$r1->json('data.id')}/execute");
        $r3->assertStatus(200)->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseCount('tasks', 1);
        $this->assertEquals('Task created successfully.', $r3->json('data.execution_result.summary'));
    }
}
