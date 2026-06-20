<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Fluxio\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * update_task_status — proposal-time interpretation and readiness.
 *
 * The proposal is ready only when the target task is uniquely resolved AND a valid
 * target status is known. Unknown/ambiguous task and missing status all keep it draft.
 */
class UpdateTaskStatusProposalTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    // ── Intent recognition ────────────────────────────────────────────────────

    public function test_mark_as_completed_produces_update_task_status_intent(): void
    {
        Task::factory()->create(['title' => 'Follow-up']);
        $this->actingAsUser();

        $this->postJson('/api/actions/interpret', ['text' => 'Mark the Follow-up task as completed'])
            ->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_task_status');
    }

    public function test_complete_the_task_produces_update_task_status_intent(): void
    {
        Task::factory()->create(['title' => 'Follow-up']);
        $this->actingAsUser();

        $this->postJson('/api/actions/interpret', ['text' => 'Complete the Follow-up task'])
            ->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_task_status');
    }

    public function test_set_to_status_produces_update_task_status_intent(): void
    {
        Task::factory()->create(['title' => 'Follow-up']);
        $this->actingAsUser();

        $this->postJson('/api/actions/interpret', ['text' => 'Set the Follow-up task to completed'])
            ->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_task_status');
    }

    // ── Known task + valid status → ready ─────────────────────────────────────

    public function test_known_task_and_valid_status_is_ready(): void
    {
        Task::factory()->create(['title' => 'Follow-up Rossi', 'status' => 'pending']);
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Mark the Follow-up Rossi task as completed',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_task_status')
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.ambiguities', []);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Follow-up Rossi', $fields['task']['value']);
        $this->assertEquals('completed', $fields['state']['value']);
    }

    public function test_ready_proposal_has_changes_with_correct_module_and_operation(): void
    {
        Task::factory()->create(['title' => 'Follow-up Rossi']);
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Mark the Follow-up Rossi task as completed',
        ]);

        $changes = $response->json('data.changes');
        $this->assertNotEmpty($changes);
        $this->assertEquals('tasks', $changes[0]['module']);
        $this->assertEquals('update', $changes[0]['type']);
    }

    public function test_done_is_normalized_to_completed(): void
    {
        Task::factory()->create(['title' => 'Follow-up Rossi']);
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Mark the Follow-up Rossi task as done',
        ]);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('completed', $fields['state']['value']);
    }

    // ── Unknown task → draft (missing) ────────────────────────────────────────

    public function test_unknown_task_is_not_ready(): void
    {
        // No task titled 'Ghost' exists.
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Mark the Ghost task as completed',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_task_status')
            ->assertJsonPath('data.status', 'draft');

        $missingKeys = collect($response->json('data.missing'))->pluck('key')->all();
        $this->assertContains('task', $missingKeys);
    }

    // ── Ambiguous task → draft with blocking ambiguity ────────────────────────

    public function test_ambiguous_task_is_not_ready_and_exposes_blocking_ambiguity(): void
    {
        Task::factory()->create(['title' => 'Follow-up Rossi']);
        Task::factory()->create(['title' => 'Follow-up Bianchi']);
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Mark the Follow-up task as completed',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_task_status')
            ->assertJsonPath('data.status', 'draft');

        // Task is a blocking ambiguity, not a plain missing field.
        $missingKeys = collect($response->json('data.missing'))->pluck('key')->all();
        $this->assertNotContains('task', $missingKeys);

        $ambiguities = $response->json('data.ambiguities');
        $this->assertNotEmpty($ambiguities);
        $taskAmbiguity = collect($ambiguities)->firstWhere('key', 'task');
        $this->assertNotNull($taskAmbiguity);
        $this->assertTrue($taskAmbiguity['blocking']);
        $this->assertNull($taskAmbiguity['selected_candidate_id']);
        $this->assertCount(2, $taskAmbiguity['candidates']);
    }

    // ── Missing status → draft ────────────────────────────────────────────────

    public function test_missing_status_is_not_ready(): void
    {
        Task::factory()->create(['title' => 'Follow-up']);
        $this->actingAsUser();

        // An explicit update verb with no recognizable target status.
        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Update the Follow-up task',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_task_status')
            ->assertJsonPath('data.status', 'draft');

        $missingKeys = collect($response->json('data.missing'))->pluck('key')->all();
        $this->assertContains('state', $missingKeys);
        // The task itself was resolved, so it is not missing.
        $this->assertNotContains('task', $missingKeys);
    }

    // ── Canonical phrase ──────────────────────────────────────────────────────

    public function test_ready_proposal_exposes_canonical_phrase(): void
    {
        Task::factory()->create(['title' => 'Follow-up Rossi']);
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Mark the Follow-up Rossi task as completed',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.canonical_phrase', 'Mark task Follow-up Rossi as completed.');
    }

    public function test_draft_proposal_has_no_canonical_phrase(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Mark the Ghost task as completed',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.canonical_phrase', null);
    }

    // ── Provider blindness: no ids leaked into the proposal entities ──────────

    public function test_proposal_entities_carry_no_task_id(): void
    {
        Task::factory()->create(['title' => 'Follow-up Rossi']);
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Mark the Follow-up Rossi task as completed',
        ]);

        $entities = $response->json('data.entities');
        $this->assertArrayNotHasKey('task_id', $entities);
        $this->assertArrayNotHasKey('task_query', $entities);
    }

    // ── create_task remains unaffected ────────────────────────────────────────

    public function test_create_task_intent_is_unchanged(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/actions/interpret', ['text' => 'Create a task for Rossini'])
            ->assertStatus(200)
            ->assertJsonPath('data.intent', 'create_task');
    }
}
