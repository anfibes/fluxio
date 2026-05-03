<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfirmActionProposalTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function interpretCommand(string $text): array
    {
        $response = $this->postJson('/api/actions/interpret', ['text' => $text]);
        $response->assertStatus(200);

        return $response->json('data');
    }

    // --- persistence ---

    public function test_interpret_persists_ready_proposal(): void
    {
        $user = $this->actingAsUser();

        $data = $this->interpretCommand('Create a task for Rossini');

        $this->assertNotEmpty($data['id']);
        $this->assertDatabaseHas('action_proposals', [
            'id' => $data['id'],
            'intent' => 'create_task',
            'status' => 'ready',
            'user_id' => $user->id,
        ]);
    }

    public function test_interpret_persists_draft_proposal(): void
    {
        $this->actingAsUser();

        $data = $this->interpretCommand('Schedule a call with Rossini');

        $this->assertDatabaseHas('action_proposals', [
            'id' => $data['id'],
            'status' => 'draft',
        ]);

        $missingKeys = collect($data['missing'])->pluck('key');
        $this->assertContains('date', $missingKeys);
        $this->assertContains('time', $missingKeys);
    }

    // --- confirm ready proposal ---

    public function test_ready_proposal_can_be_confirmed(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretCommand('Create a task for Rossini');

        $response = $this->postJson("/api/actions/{$proposal['id']}/confirm");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertNotNull($response->json('data.confirmed_at'));

        $this->assertDatabaseHas('action_proposals', [
            'id' => $proposal['id'],
            'status' => 'confirmed',
        ]);
    }

    public function test_confirm_returns_confirmed_message(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretCommand('Create a task for Rossini');

        $this->postJson("/api/actions/{$proposal['id']}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Action proposal confirmed successfully.');
    }

    // --- confirm draft proposal ---

    public function test_draft_proposal_cannot_be_confirmed(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretCommand('Schedule a call with Rossini');

        $response = $this->postJson("/api/actions/{$proposal['id']}/confirm");

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['proposal']]);

        $this->assertDatabaseHas('action_proposals', [
            'id' => $proposal['id'],
            'status' => 'draft',
        ]);
    }

    // --- idempotency ---

    public function test_confirmed_proposal_can_be_confirmed_again_idempotently(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretCommand('Create a task for Rossini');

        $this->postJson("/api/actions/{$proposal['id']}/confirm")->assertStatus(200);

        $this->postJson("/api/actions/{$proposal['id']}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');
    }

    // --- ownership ---

    public function test_user_cannot_confirm_another_users_proposal(): void
    {
        $userA = User::factory()->create();
        Sanctum::actingAs($userA);

        $proposal = $this->interpretCommand('Create a task for Rossini');
        $proposalId = $proposal['id'];

        $userB = User::factory()->create();
        Sanctum::actingAs($userB);

        $this->postJson("/api/actions/{$proposalId}/confirm")
            ->assertStatus(404);
    }

    // --- not found ---

    public function test_missing_proposal_returns_404(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/actions/00000000-0000-0000-0000-000000000000/confirm')
            ->assertStatus(404);
    }

    // --- unauthenticated ---

    public function test_unauthenticated_confirm_returns_401(): void
    {
        $this->postJson('/api/actions/some-id/confirm')
            ->assertStatus(401);
    }

    // --- execution boundary ---

    public function test_confirming_ready_proposal_does_not_create_tasks(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretCommand('Create a task for Rossini');

        $this->postJson("/api/actions/{$proposal['id']}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_confirming_ready_proposal_does_not_set_execution_fields(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretCommand('Create a task for Rossini');

        $response = $this->postJson("/api/actions/{$proposal['id']}/confirm");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertNotNull($response->json('data.confirmed_at'));
        $this->assertNull($response->json('data.executed_at'));
        $this->assertNull($response->json('data.failed_at'));
        $this->assertNull($response->json('data.failure_reason'));
    }

    // --- idempotency at data level ---

    public function test_second_confirm_does_not_change_confirmed_at(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretCommand('Create a task for Rossini');

        $first = $this->postJson("/api/actions/{$proposal['id']}/confirm");
        $firstConfirmedAt = $first->json('data.confirmed_at');

        $second = $this->postJson("/api/actions/{$proposal['id']}/confirm");
        $secondConfirmedAt = $second->json('data.confirmed_at');

        $this->assertEquals($firstConfirmedAt, $secondConfirmedAt);
        $this->assertEquals('confirmed', $second->json('data.status'));
    }

    // --- terminal status guards ---

    public function test_executed_proposal_cannot_be_confirmed(): void
    {
        $user = $this->actingAsUser();

        $proposal = ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'create_task',
            'status' => 'executed',
            'confidence' => 0.9,
            'source_text' => 'Create a task for Rossini',
            'entities' => ['lead' => 'Rossini'],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [],
            'changes' => [],
            'needs_confirmation' => true,
        ]);

        $response = $this->postJson("/api/actions/{$proposal->id}/confirm");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['proposal']]);

        $this->assertDatabaseHas('action_proposals', [
            'id' => $proposal->id,
            'status' => 'executed',
        ]);
    }

    public function test_failed_proposal_cannot_be_confirmed(): void
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

        $response = $this->postJson("/api/actions/{$proposal->id}/confirm");

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['proposal']]);

        $this->assertDatabaseHas('action_proposals', [
            'id' => $proposal->id,
            'status' => 'failed',
        ]);
    }
}
