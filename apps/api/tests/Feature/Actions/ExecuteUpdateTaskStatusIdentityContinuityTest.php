<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Identity continuity (slice 2) — update_task_status consumes `resolved_entities`:
 * the task selected or auto-resolved on the proposal is exactly the row the
 * executor updates, by primary key, title never compared.
 *
 * Mirrors ExecuteUpdateLeadStatusIdentityContinuityTest. Legacy proposals
 * (resolved_entities === null) keep the previous textual re-resolution; contract
 * proposals ([] or map) never fall back to the title.
 */
class ExecuteUpdateTaskStatusIdentityContinuityTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * A confirmed update_task_status proposal under the identity-continuity
     * contract. $resolvedEntities: null (legacy), [] (contract, no identity),
     * or a role-keyed identity map.
     */
    private function confirmedProposal(User $owner, string $taskTitle, string $state, ?array $resolvedEntities): ActionProposal
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
            'resolved_entities' => $resolvedEntities,
        ]);
    }

    // ── 1. Auto-resolved task → execution by primary key ─────────────────────

    public function test_auto_resolved_task_updates_the_exact_record_by_id(): void
    {
        Task::factory()->create(['title' => 'Unrelated chores', 'status' => 'pending']);
        $target = Task::factory()->create(['title' => 'Prepare quote Rossini', 'status' => 'pending']);
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Mark the Prepare quote Rossini task as completed']);
        $interpret->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_task_status')
            ->assertJsonPath('data.status', 'ready');

        $proposalId = $interpret->json('data.id');
        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);
        $this->postJson("/api/actions/{$proposalId}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('tasks', ['id' => $target->id, 'status' => 'completed']);
        $this->assertDatabaseHas('tasks', ['title' => 'Unrelated chores', 'status' => 'pending']);
    }

    // ── 2. Homonymous titles: the user's selection is what executes ──────────

    public function test_homonymous_tasks_execute_against_the_selected_candidate(): void
    {
        // Two tasks with the SAME title — textual re-resolution could never
        // distinguish them (it used to 422 forever on this scenario).
        $tasks = Task::factory()->count(2)->create(['title' => 'Follow-up', 'status' => 'pending']);
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Mark the Follow-up task as completed']);
        $interpret->assertStatus(200)->assertJsonPath('data.status', 'draft');

        $candidates = $interpret->json('data.ambiguities.0.candidates');
        $this->assertCount(2, $candidates);

        // Derive target and bystander from the ACTUAL candidate order surfaced to
        // the user — no reliance on implicit database ordering.
        $selectedId = $candidates[1]['id'];
        $bystanderId = $candidates[0]['id'];
        $this->assertEqualsCanonicalizing($tasks->pluck('id')->all(), [$selectedId, $bystanderId]);

        $proposalId = $interpret->json('data.id');

        // Deterministic ordinal selection of the second homonym.
        $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'the second one'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.last_refinement.ambiguity_outcome.kind', 'resolved');

        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);

        // The duplicated title must not block execution: the executor acts by id.
        $this->postJson("/api/actions/{$proposalId}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('tasks', ['id' => $selectedId, 'status' => 'completed']);
        $this->assertDatabaseHas('tasks', ['id' => $bystanderId, 'status' => 'pending']);
    }

    // ── 3. Rename after confirmation: same identity, same row ────────────────

    public function test_renamed_task_still_updates_the_same_record(): void
    {
        $target = Task::factory()->create(['title' => 'Prepare quote Rossini', 'status' => 'pending']);
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Mark the Prepare quote Rossini task as completed']);
        $interpret->assertStatus(200)->assertJsonPath('data.status', 'ready');

        $proposalId = $interpret->json('data.id');
        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);

        // Rename between confirmation and execution — the label snapshot goes
        // stale, the identity does not. The old title matches nothing anymore.
        $target->update(['title' => 'Completely different title']);

        $this->postJson("/api/actions/{$proposalId}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('tasks', [
            'id' => $target->id,
            'title' => 'Completely different title',
            'status' => 'completed',
        ]);
    }

    // ── 4. Delete after confirmation: safe 422, proposal stays confirmed ─────

    public function test_deleted_task_fails_safely_and_touches_nothing(): void
    {
        $bystander = Task::factory()->create(['title' => 'Bystander task', 'status' => 'pending']);
        $target = Task::factory()->create(['title' => 'Prepare quote Rossini', 'status' => 'pending']);
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Mark the Prepare quote Rossini task as completed']);
        $interpret->assertStatus(200)->assertJsonPath('data.status', 'ready');

        $proposalId = $interpret->json('data.id');
        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);

        $target->delete();

        $this->postJson("/api/actions/{$proposalId}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['task']]);

        // Current failure semantics preserved: confirmed, no terminal failure.
        $refreshed = ActionProposal::findOrFail($proposalId);
        $this->assertSame('confirmed', $refreshed->status);
        $this->assertNull($refreshed->failed_at);
        $this->assertNull($refreshed->failure_reason_code);

        $this->assertDatabaseHas('tasks', ['id' => $bystander->id, 'status' => 'pending']);
    }

    // ── 5. Contract proposal without identity: no textual fallback ───────────

    public function test_contract_proposal_without_identity_does_not_fall_back_to_title(): void
    {
        $task = Task::factory()->create(['title' => 'Follow-up Rossi', 'status' => 'pending']);
        $actor = $this->actingAsUser();

        // [] = built under the contract, task never resolved. The label points at
        // an existing row, but title re-resolution is reserved for legacy (null).
        $proposal = $this->confirmedProposal($actor, 'Follow-up Rossi', 'completed', []);

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['task']]);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'pending']);
        $this->assertSame('confirmed', ActionProposal::findOrFail($proposal->id)->status);
    }

    // ── 6. Malformed identity entry: not legacy, no fallback ─────────────────

    public function test_malformed_identity_entry_is_not_treated_as_legacy(): void
    {
        $task = Task::factory()->create(['title' => 'Follow-up Rossi', 'status' => 'pending']);
        $actor = $this->actingAsUser();

        // Structure without a valid id: invalid identity, NOT a legacy marker —
        // must fail safely instead of silently re-resolving the title.
        $proposal = $this->confirmedProposal($actor, 'Follow-up Rossi', 'completed', [
            'task' => ['type' => 'task', 'label' => 'Follow-up Rossi'],
        ]);

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['task']]);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'pending']);
    }

    // ── 7. Identity is the only lookup key: the title is never consulted ─────

    public function test_execution_follows_the_identity_not_the_label(): void
    {
        $target = Task::factory()->create(['title' => 'Alpha task', 'status' => 'pending']);
        $decoy = Task::factory()->create(['title' => 'Beta task', 'status' => 'pending']);
        $actor = $this->actingAsUser();

        // Incoherent fixture: identity points at the Alpha task, label says Beta.
        // The id is the identity authority; the label is presentation-only and
        // must not redirect execution to a different row.
        $proposal = $this->confirmedProposal($actor, 'Beta task', 'completed', [
            'task' => ['id' => $target->id, 'type' => 'task', 'label' => 'Alpha task'],
        ]);

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('tasks', ['id' => $target->id, 'status' => 'completed']);
        $this->assertDatabaseHas('tasks', ['id' => $decoy->id, 'status' => 'pending']);
    }

    // ── 8. Legacy proposal (null): textual fallback preserved ────────────────

    public function test_legacy_proposal_with_null_resolved_entities_uses_textual_fallback(): void
    {
        $task = Task::factory()->create(['title' => 'Follow-up Rossi', 'status' => 'pending']);
        $actor = $this->actingAsUser();

        // EXPLICIT LEGACY FIXTURE: resolved_entities === null simulates a proposal
        // persisted before the identity-continuity contract. This test exercises
        // the legacy title re-resolution branch (resolveTaskByTitleLegacy) and can
        // be deleted together with that branch.
        $proposal = $this->confirmedProposal($actor, 'Follow-up Rossi', 'completed', null);

        $this->assertNull(ActionProposal::findOrFail($proposal->id)->resolved_entities);

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'completed']);
    }
}
