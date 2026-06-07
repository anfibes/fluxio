<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Intent Narration — slice 2 (end-to-end).
 *
 * Proves the additive, read-only `canonical_phrase` field flows through the real
 * API response and follows the existing backend locale mechanism (SetApiLocale →
 * Accept-Language), exactly like other localized atoms. No runtime/lifecycle
 * behavior is changed by exposing it.
 */
class CanonicalPhraseResponseTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function readyScheduleMeeting(User $user): ActionProposal
    {
        return ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'schedule_meeting',
            'status' => 'ready',
            'confidence' => 0.85,
            'source_text' => 'Meeting with Rossi SRL',
            'entities' => ['lead' => 'Rossi SRL'],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossi SRL', 'source' => 'resolved', 'required' => true],
                ['key' => 'date', 'label' => 'Date', 'value' => '2026-05-11', 'source' => 'detected', 'required' => true],
                ['key' => 'time', 'label' => 'Time', 'value' => '10:00', 'source' => 'detected', 'required' => true],
            ],
            'changes' => [
                ['type' => 'schedule', 'label' => 'Schedule Meeting', 'module' => 'calendar', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [],
        ]);
    }

    public function test_interpret_response_includes_canonical_phrase_key(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Create a task for tomorrow']);

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => ['canonical_phrase']]);
    }

    public function test_canonical_phrase_is_null_when_required_data_missing(): void
    {
        $this->actingAsUser();

        // create_task with no lead → canonical template needs :lead → null.
        $response = $this->postJson('/api/actions/interpret', ['text' => 'Create a task for tomorrow']);

        $response->assertStatus(200);
        $response->assertJsonPath('data.intent', 'create_task');
        $response->assertJsonPath('data.canonical_phrase', null);
    }

    public function test_canonical_phrase_renders_expected_english_phrase(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->readyScheduleMeeting($user);

        // A refine call returns the full resource. "High priority" is unsupported
        // for schedule_meeting, so it changes no fields — the proposal stays ready
        // and the phrase renders from the unchanged, complete state.
        $response = $this->postJson(
            "/api/actions/{$proposal->id}/refine",
            ['text' => 'High priority'],
            ['Accept-Language' => 'en'],
        );

        $response->assertStatus(200);
        $response->assertJsonPath(
            'data.canonical_phrase',
            'Schedule a meeting with Rossi SRL on 2026-05-11 at 10:00.',
        );
    }

    public function test_canonical_phrase_respects_italian_accept_language(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->readyScheduleMeeting($user);

        $response = $this->postJson(
            "/api/actions/{$proposal->id}/refine",
            ['text' => 'High priority'],
            ['Accept-Language' => 'it-IT'],
        );

        $response->assertStatus(200);
        $response->assertJsonPath(
            'data.canonical_phrase',
            'Pianifica un appuntamento con Rossi SRL il 2026-05-11 alle 10:00.',
        );
    }

    public function test_existing_response_shape_remains_stable(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Create a task for tomorrow']);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id', 'intent', 'status', 'confidence', 'source_text', 'entities',
                'missing', 'warnings', 'editable_fields', 'changes', 'needs_confirmation',
                'execution_result', 'last_refinement', 'ambiguities', 'capabilities',
                'canonical_phrase',
            ],
        ]);
    }
}
