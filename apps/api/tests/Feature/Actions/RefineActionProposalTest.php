<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RefineActionProposalTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function scheduleCallProposal(User $user, string $status = 'draft'): ActionProposal
    {
        return ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'schedule_call',
            'status' => $status,
            'confidence' => 0.7,
            'source_text' => 'Schedule a call with Rossini',
            'entities' => ['lead' => 'Rossini'],
            'missing' => [
                ['key' => 'date', 'label' => 'Date', 'required' => true],
                ['key' => 'time', 'label' => 'Time', 'required' => true],
            ],
            'warnings' => [],
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossini', 'source' => 'detected', 'required' => true],
                ['key' => 'date', 'label' => 'Date', 'value' => null, 'source' => 'missing', 'required' => true],
                ['key' => 'time', 'label' => 'Time', 'value' => null, 'source' => 'missing', 'required' => true],
            ],
            'changes' => [
                ['type' => 'schedule', 'label' => 'Schedule call', 'module' => 'calendar', 'payload' => []],
            ],
            'needs_confirmation' => true,
        ]);
    }

    // --- happy path ---

    public function test_draft_schedule_call_can_be_refined_with_tomorrow_morning(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->scheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", [
            'text' => 'Tomorrow morning',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('message', 'Action proposal refined successfully.');
    }

    public function test_refinement_returns_same_proposal_id(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->scheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", [
            'text' => 'Tomorrow morning',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $proposal->id);
    }

    public function test_refinement_removes_date_and_time_from_missing(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->scheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", [
            'text' => 'Tomorrow morning',
        ]);

        $response->assertStatus(200);

        $missing = $response->json('data.missing');
        $missingKeys = collect($missing)->pluck('key');

        $this->assertNotContains('date', $missingKeys);
        $this->assertNotContains('time', $missingKeys);
    }

    public function test_refinement_sets_status_to_ready(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->scheduleCallProposal($user);

        $this->postJson("/api/actions/{$proposal->id}/refine", [
            'text' => 'Tomorrow morning',
        ])->assertJsonPath('data.status', 'ready');

        $this->assertDatabaseHas('action_proposals', [
            'id' => $proposal->id,
            'status' => 'ready',
        ]);
    }

    public function test_refinement_sets_date_and_time_editable_fields(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->scheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", [
            'text' => 'Tomorrow morning',
        ]);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');

        $this->assertEquals(now()->addDay()->toDateString(), $fields['date']['value']);
        $this->assertEquals('09:00', $fields['time']['value']);
        $this->assertEquals('detected', $fields['date']['source']);
        $this->assertEquals('detected', $fields['time']['source']);
    }

    public function test_refinement_increases_confidence_to_at_least_0_85(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->scheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", [
            'text' => 'Tomorrow morning',
        ]);

        $this->assertGreaterThanOrEqual(0.85, $response->json('data.confidence'));
    }

    public function test_ready_proposal_can_also_be_refined(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->scheduleCallProposal($user, 'ready');

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", [
            'text' => 'Tomorrow morning',
        ]);

        $response->assertStatus(200);
    }

    // --- unrecognized refinement ---

    public function test_unrecognized_refinement_adds_warning_and_preserves_status(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->scheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", [
            'text' => 'Some unrelated text',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'draft');

        $warnings = $response->json('data.warnings');
        $this->assertNotEmpty($warnings);
        $this->assertContains('The refinement could not be applied. The proposal was left unchanged.', $warnings);
    }

    // --- ownership ---

    public function test_non_owner_gets_404(): void
    {
        $userA = User::factory()->create();
        Sanctum::actingAs($userA);
        $proposal = $this->scheduleCallProposal($userA);

        $userB = User::factory()->create();
        Sanctum::actingAs($userB);

        $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Tomorrow morning'])
            ->assertStatus(404);
    }

    // --- terminal status guards ---

    public function test_confirmed_proposal_cannot_be_refined(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->scheduleCallProposal($user, 'confirmed');

        $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Tomorrow morning'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['proposal']]);

        $this->assertDatabaseHas('action_proposals', ['id' => $proposal->id, 'status' => 'confirmed']);
    }

    public function test_executed_proposal_cannot_be_refined(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->scheduleCallProposal($user, 'executed');

        $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Tomorrow morning'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['proposal']]);

        $this->assertDatabaseHas('action_proposals', ['id' => $proposal->id, 'status' => 'executed']);
    }

    // --- validation ---

    public function test_missing_text_returns_422(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->scheduleCallProposal($user);

        $this->postJson("/api/actions/{$proposal->id}/refine", [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['text']]);
    }

    // --- unauthenticated ---

    public function test_unauthenticated_refine_returns_401(): void
    {
        $this->postJson('/api/actions/00000000-0000-0000-0000-000000000000/refine', ['text' => 'x'])
            ->assertStatus(401);
    }
}
