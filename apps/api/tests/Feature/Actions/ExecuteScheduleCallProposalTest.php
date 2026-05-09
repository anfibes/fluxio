<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExecuteScheduleCallProposalTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function confirmedScheduleCallProposal(User $user): ActionProposal
    {
        return ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'schedule_call',
            'status' => 'confirmed',
            'confidence' => 0.85,
            'source_text' => 'Schedule a call with Rossini',
            'entities' => ['lead' => 'Rossini'],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossini', 'source' => 'detected', 'required' => true],
                ['key' => 'date', 'label' => 'Date', 'value' => '2026-05-10', 'source' => 'detected', 'required' => true],
                ['key' => 'time', 'label' => 'Time', 'value' => '09:00', 'source' => 'detected', 'required' => true],
            ],
            'changes' => [
                ['type' => 'schedule', 'label' => 'Schedule call', 'module' => 'calendar', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'confirmed_at' => now(),
        ]);
    }

    // --- happy path ---

    public function test_confirmed_schedule_call_proposal_executes_successfully(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->confirmedScheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Action executed successfully.');
    }

    public function test_executed_proposal_status_becomes_executed(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->confirmedScheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('action_proposals', [
            'id' => $proposal->id,
            'status' => 'executed',
        ]);
    }

    public function test_execution_result_contains_type_scheduled_call(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->confirmedScheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $response->assertStatus(200)
            ->assertJsonPath('data.execution_result.type', 'scheduled_call')
            ->assertJsonPath('data.execution_result.status', 'scheduled');
    }

    public function test_execution_result_contains_lead_date_and_time(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->confirmedScheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $response->assertStatus(200)
            ->assertJsonPath('data.execution_result.lead', 'Rossini')
            ->assertJsonPath('data.execution_result.date', '2026-05-10')
            ->assertJsonPath('data.execution_result.time', '09:00');
    }

    public function test_executed_at_is_set(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->confirmedScheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $this->assertNotNull($response->json('data.executed_at'));
        $this->assertNotNull(ActionProposal::find($proposal->id)->executed_at);
    }

    // --- idempotency ---

    public function test_repeated_execution_is_idempotent(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->confirmedScheduleCallProposal($user);

        $first = $this->postJson("/api/actions/{$proposal->id}/execute");
        $firstResult = $first->json('data.execution_result');

        $second = $this->postJson("/api/actions/{$proposal->id}/execute");
        $secondResult = $second->json('data.execution_result');

        $second->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertEquals($firstResult, $secondResult);
    }

    // --- end-to-end via API (interpret → refine → confirm → execute) ---

    public function test_full_flow_schedule_call_with_refinement(): void
    {
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', [
            'text' => 'Schedule a call with Rossini',
        ]);
        $interpret->assertStatus(200);
        $this->assertEquals('draft', $interpret->json('data.status'));

        $id = $interpret->json('data.id');

        $refine = $this->postJson("/api/actions/{$id}/refine", [
            'text' => 'Tomorrow morning',
        ]);
        $refine->assertStatus(200)
            ->assertJsonPath('data.status', 'ready');

        $this->postJson("/api/actions/{$id}/confirm")->assertStatus(200);

        $execute = $this->postJson("/api/actions/{$id}/execute");
        $execute->assertStatus(200)
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.execution_result.type', 'scheduled_call')
            ->assertJsonPath('data.execution_result.status', 'scheduled');
    }

    // --- lifecycle guards ---

    public function test_draft_schedule_call_cannot_be_executed(): void
    {
        $user = $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', [
            'text' => 'Schedule a call with Rossini',
        ]);
        $interpret->assertStatus(200);
        $this->assertEquals('draft', $interpret->json('data.status'));

        $this->postJson("/api/actions/{$interpret->json('data.id')}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['proposal']]);
    }
}
