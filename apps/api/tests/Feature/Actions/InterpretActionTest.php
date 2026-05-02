<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InterpretActionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    // --- authentication ---

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->postJson('/api/actions/interpret', ['text' => 'Create a task'])
            ->assertStatus(401);
    }

    // --- validation ---

    public function test_missing_text_returns_422(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/actions/interpret', [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['text']]);
    }

    public function test_text_too_short_returns_422(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/actions/interpret', ['text' => 'Hi'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['text']]);
    }

    // --- create_task intent ---

    public function test_create_task_with_lead_returns_ready_proposal(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Create a task for Rossini',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Command interpreted successfully.')
            ->assertJsonPath('data.intent', 'create_task')
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.needs_confirmation', true);

        $this->assertGreaterThanOrEqual(0.0, $response->json('data.confidence'));
        $this->assertLessThanOrEqual(1.0, $response->json('data.confidence'));
    }

    public function test_create_task_without_lead_returns_draft_proposal(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Create a task for tomorrow',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'create_task')
            ->assertJsonPath('data.status', 'draft');
    }

    // --- schedule_call intent ---

    public function test_schedule_call_returns_draft_with_missing_fields(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Schedule a call with Rossini',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'schedule_call')
            ->assertJsonPath('data.status', 'draft');

        $missing = $response->json('data.missing');
        $this->assertContains('date', $missing);
        $this->assertContains('time', $missing);
    }

    // --- unknown intent ---

    public function test_unrecognized_command_returns_unknown_intent(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Show me the dashboard',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'unknown')
            ->assertJsonPath('data.status', 'draft');
    }

    // --- proposal shape ---

    public function test_proposal_contains_all_required_fields(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Create a task for Rossini',
        ]);

        $data = $response->json('data');

        foreach (['id', 'intent', 'status', 'confidence', 'source_text', 'entities', 'missing', 'warnings', 'editable_fields', 'changes', 'needs_confirmation'] as $field) {
            $this->assertArrayHasKey($field, $data, "Missing field: {$field}");
        }

        $this->assertEquals('Create a task for Rossini', $data['source_text']);
        $this->assertIsString($data['id']);
        $this->assertNotEmpty($data['id']);
    }

    public function test_lead_entity_is_detected_when_rossini_present(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Create a task for Rossini',
        ]);

        $response->assertStatus(200);

        $this->assertEquals('Rossini', $response->json('data.entities.lead'));
    }

    public function test_confidence_is_higher_for_create_task_than_unknown(): void
    {
        $this->actingAsUser();

        $taskResponse = $this->postJson('/api/actions/interpret', [
            'text' => 'Create a task for Rossini',
        ]);

        $unknownResponse = $this->postJson('/api/actions/interpret', [
            'text' => 'Show me the dashboard',
        ]);

        $this->assertGreaterThan(
            $unknownResponse->json('data.confidence'),
            $taskResponse->json('data.confidence')
        );
    }
}
