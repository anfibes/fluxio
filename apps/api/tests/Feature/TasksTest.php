<?php

namespace Tests\Feature;

use App\Models\User;
use Fluxio\Leads\Models\Lead;
use Fluxio\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TasksTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    // --- authentication ---

    public function test_unauthenticated_access_returns_401(): void
    {
        $this->getJson('/api/tasks')->assertStatus(401);
        $this->postJson('/api/tasks', [])->assertStatus(401);
        $this->getJson('/api/tasks/1')->assertStatus(401);
        $this->patchJson('/api/tasks/1', [])->assertStatus(401);
        $this->deleteJson('/api/tasks/1')->assertStatus(401);
    }

    // --- index ---

    public function test_authenticated_user_can_list_tasks(): void
    {
        $this->actingAsUser();
        Task::factory()->count(3)->create();

        $response = $this->getJson('/api/tasks');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Tasks retrieved successfully.')
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_index_returns_empty_list_when_no_tasks(): void
    {
        $this->actingAsUser();

        $response = $this->getJson('/api/tasks');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 0);

        $this->assertCount(0, $response->json('data'));
    }

    public function test_index_respects_per_page_parameter(): void
    {
        $this->actingAsUser();
        Task::factory()->count(5)->create();

        $response = $this->getJson('/api/tasks?per_page=2');

        $response->assertStatus(200)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3);

        $this->assertCount(2, $response->json('data'));
    }

    // --- search ---

    public function test_index_supports_search_by_title(): void
    {
        $this->actingAsUser();
        Task::factory()->create(['title' => 'Follow up with client']);
        Task::factory()->create(['title' => 'Update documentation']);

        $response = $this->getJson('/api/tasks?search=Follow');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Follow up with client');
    }

    public function test_index_supports_search_by_description(): void
    {
        $this->actingAsUser();
        Task::factory()->create(['title' => 'Task A', 'description' => 'Call the client on Monday']);
        Task::factory()->create(['title' => 'Task B', 'description' => 'Write quarterly report']);

        $response = $this->getJson('/api/tasks?search=quarterly');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.title', 'Task B');
    }

    public function test_index_search_excludes_unrelated_tasks(): void
    {
        $this->actingAsUser();
        Task::factory()->create(['title' => 'Follow up with client']);
        Task::factory()->create(['title' => 'Update docs']);
        Task::factory()->create(['title' => 'Send invoice']);

        $response = $this->getJson('/api/tasks?search=Follow');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertContains('Follow up with client', $titles);
        $this->assertNotContains('Update docs', $titles);
        $this->assertNotContains('Send invoice', $titles);
    }

    // --- filters ---

    public function test_index_supports_status_filter(): void
    {
        $this->actingAsUser();
        Task::factory()->create(['status' => 'pending']);
        Task::factory()->create(['status' => 'completed']);
        Task::factory()->create(['status' => 'completed']);

        $response = $this->getJson('/api/tasks?status=completed');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_index_supports_priority_filter(): void
    {
        $this->actingAsUser();
        Task::factory()->create(['priority' => 'low']);
        Task::factory()->create(['priority' => 'high']);
        Task::factory()->create(['priority' => 'high']);

        $response = $this->getJson('/api/tasks?priority=high');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_index_supports_lead_id_filter(): void
    {
        $this->actingAsUser();
        $lead = Lead::factory()->create();
        Task::factory()->create(['lead_id' => $lead->id]);
        Task::factory()->create(['lead_id' => $lead->id]);
        Task::factory()->create(['lead_id' => null]);

        $response = $this->getJson("/api/tasks?lead_id={$lead->id}");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_index_lead_id_filter_with_null_returns_unassigned(): void
    {
        $this->actingAsUser();
        $lead = Lead::factory()->create();
        Task::factory()->create(['lead_id' => $lead->id]);
        Task::factory()->create(['lead_id' => null]);

        $response = $this->getJson('/api/tasks?lead_id=');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    // --- store ---

    public function test_authenticated_user_can_create_a_task(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/tasks', [
            'title' => 'Send proposal',
            'description' => 'Prepare and send the project proposal.',
            'status' => 'pending',
            'priority' => 'high',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Task created successfully.')
            ->assertJsonPath('data.title', 'Send proposal')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.priority', 'high');

        $this->assertDatabaseHas('tasks', ['title' => 'Send proposal']);
    }

    public function test_create_defaults_status_to_pending_when_omitted(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/tasks', ['title' => 'Minimal task']);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.priority', 'normal');

        $this->assertDatabaseHas('tasks', ['title' => 'Minimal task', 'status' => 'pending', 'priority' => 'normal']);
    }

    public function test_create_can_associate_task_with_lead(): void
    {
        $this->actingAsUser();
        $lead = Lead::factory()->create();

        $response = $this->postJson('/api/tasks', [
            'title' => 'Follow up task',
            'lead_id' => $lead->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.lead_id', $lead->id);

        $this->assertDatabaseHas('tasks', ['title' => 'Follow up task', 'lead_id' => $lead->id]);
    }

    public function test_create_returns_422_on_missing_title(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/tasks', ['description' => 'No title']);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['title']]);
    }

    public function test_create_returns_422_on_invalid_status(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/tasks', [
            'title' => 'Bad task',
            'status' => 'invalid-status',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['status']]);
    }

    public function test_create_returns_422_on_invalid_priority(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/tasks', [
            'title' => 'Bad task',
            'priority' => 'urgent',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['priority']]);
    }

    public function test_create_returns_422_on_nonexistent_lead_id(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/tasks', [
            'title' => 'Orphan task',
            'lead_id' => 9999,
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['lead_id']]);
    }

    // --- show ---

    public function test_authenticated_user_can_view_a_task(): void
    {
        $this->actingAsUser();
        $task = Task::factory()->create([
            'title' => 'Review contract',
            'status' => 'in_progress',
            'priority' => 'high',
        ]);

        $response = $this->getJson("/api/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Task retrieved successfully.')
            ->assertJsonStructure([
                'data' => ['id', 'title', 'description', 'status', 'priority', 'due_at', 'lead_id', 'created_at', 'updated_at'],
            ])
            ->assertJsonPath('data.id', $task->id)
            ->assertJsonPath('data.title', 'Review contract')
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.priority', 'high');
    }

    public function test_show_returns_404_for_missing_task(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/tasks/999')->assertStatus(404);
    }

    // --- update ---

    public function test_authenticated_user_can_update_a_task(): void
    {
        $this->actingAsUser();
        $task = Task::factory()->create(['status' => 'pending', 'priority' => 'normal']);

        $response = $this->patchJson("/api/tasks/{$task->id}", [
            'status' => 'in_progress',
            'priority' => 'high',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Task updated successfully.')
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.priority', 'high');

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'in_progress', 'priority' => 'high']);
    }

    public function test_update_can_reassign_lead(): void
    {
        $this->actingAsUser();
        $lead = Lead::factory()->create();
        $task = Task::factory()->create(['lead_id' => null]);

        $response = $this->patchJson("/api/tasks/{$task->id}", ['lead_id' => $lead->id]);

        $response->assertStatus(200)
            ->assertJsonPath('data.lead_id', $lead->id);
    }

    public function test_update_can_clear_lead_id(): void
    {
        $this->actingAsUser();
        $lead = Lead::factory()->create();
        $task = Task::factory()->create(['lead_id' => $lead->id]);

        $response = $this->patchJson("/api/tasks/{$task->id}", ['lead_id' => null]);

        $response->assertStatus(200)
            ->assertJsonPath('data.lead_id', null);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'lead_id' => null]);
    }

    public function test_update_returns_422_on_invalid_status(): void
    {
        $this->actingAsUser();
        $task = Task::factory()->create();

        $response = $this->patchJson("/api/tasks/{$task->id}", ['status' => 'not-valid']);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['status']]);
    }

    public function test_update_returns_404_for_missing_task(): void
    {
        $this->actingAsUser();

        $this->patchJson('/api/tasks/999', ['status' => 'completed'])->assertStatus(404);
    }

    // --- destroy ---

    public function test_authenticated_user_can_delete_a_task(): void
    {
        $this->actingAsUser();
        $task = Task::factory()->create();

        $response = $this->deleteJson("/api/tasks/{$task->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Task deleted successfully.');

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_delete_returns_404_for_missing_task(): void
    {
        $this->actingAsUser();

        $this->deleteJson('/api/tasks/999')->assertStatus(404);
    }

    // --- resource shape ---

    public function test_task_resource_shape_is_correct(): void
    {
        $this->actingAsUser();
        $lead = Lead::factory()->create();
        $task = Task::factory()->create([
            'title' => 'Shape test task',
            'description' => 'Check the shape.',
            'status' => 'pending',
            'priority' => 'low',
            'due_at' => '2026-12-31 10:00:00',
            'lead_id' => $lead->id,
        ]);

        $response = $this->getJson("/api/tasks/{$task->id}");

        $data = $response->json('data');

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('description', $data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('priority', $data);
        $this->assertArrayHasKey('due_at', $data);
        $this->assertArrayHasKey('lead_id', $data);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('updated_at', $data);

        $this->assertEquals($lead->id, $data['lead_id']);
        $this->assertNotNull($data['due_at']);
        $this->assertNotNull($data['created_at']);
        $this->assertNotNull($data['updated_at']);
    }

    // --- lead nullification on delete ---

    public function test_deleting_lead_nullifies_task_lead_id(): void
    {
        $this->actingAsUser();
        $lead = Lead::factory()->create();
        $task = Task::factory()->create(['lead_id' => $lead->id]);

        $this->deleteJson("/api/leads/{$lead->id}")->assertStatus(200);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'lead_id' => null]);
    }
}
