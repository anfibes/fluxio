<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AmbiguousProposalTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    // --- interpretation ---

    public function test_call_rossi_creates_draft_proposal(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'schedule_call')
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_call_rossi_includes_lead_ambiguity(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);

        $response->assertStatus(200);

        $ambiguities = $response->json('data.ambiguities');
        $this->assertCount(1, $ambiguities);

        $ambiguity = $ambiguities[0];
        $this->assertEquals('lead', $ambiguity['key']);
        $this->assertEquals('multiple_matches', $ambiguity['reason']);
        $this->assertTrue($ambiguity['blocking']);
        $this->assertEquals('Rossi', $ambiguity['query']);
        $this->assertNull($ambiguity['selected_candidate_id']);
    }

    public function test_call_rossi_ambiguity_has_three_candidates(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);

        $response->assertStatus(200);

        $candidates = $response->json('data.ambiguities.0.candidates');
        $this->assertCount(3, $candidates);

        $labels = array_column($candidates, 'label');
        $this->assertContains('Mario Rossi', $labels);
        $this->assertContains('Rossi SRL', $labels);
        $this->assertContains('Studio Rossi', $labels);
    }

    public function test_call_rossi_does_not_set_lead_in_editable_fields(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);

        $response->assertStatus(200);

        $fieldKeys = array_column($response->json('data.editable_fields'), 'key');
        $this->assertNotContains('lead', $fieldKeys);
    }

    public function test_call_rossini_is_not_ambiguous(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossini']);

        $response->assertStatus(200)
            ->assertJsonPath('data.ambiguities', []);

        $fieldKeys = array_column($response->json('data.editable_fields'), 'key');
        $this->assertContains('lead', $fieldKeys);
    }

    // --- confirmation guard ---

    public function test_ambiguous_proposal_cannot_be_confirmed(): void
    {
        $user = $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $response->json('data.id');

        $this->postJson("/api/actions/{$proposalId}/confirm")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['proposal']]);
    }

    // --- refinement: exact label ---

    public function test_refinement_rossi_srl_resolves_lead_ambiguity(): void
    {
        $user = $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Rossi SRL']);

        $response->assertStatus(200);

        $ambiguity = $response->json('data.ambiguities.0');
        $this->assertEquals(7, $ambiguity['selected_candidate_id']);
    }

    public function test_refinement_rossi_srl_updates_same_proposal(): void
    {
        $user = $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Rossi SRL']);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $proposalId);
    }

    public function test_refinement_rossi_srl_sets_lead_editable_field(): void
    {
        $user = $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Rossi SRL']);

        $response->assertStatus(200);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Rossi SRL', $fields['lead']['value']);
        $this->assertEquals('detected', $fields['lead']['source']);
    }

    // --- refinement: ordinal ---

    public function test_refinement_the_second_one_resolves_to_second_candidate(): void
    {
        $user = $this->actingAsUser();

        $interpret  = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        // Candidates are sorted by confidence desc: [Rossi SRL (0.8), Mario Rossi (0.65), Studio Rossi (0.65)]
        // "The second one" → index 1 → Mario Rossi (id: 1)
        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'The second one']);

        $response->assertStatus(200)
            ->assertJsonPath('data.ambiguities.0.selected_candidate_id', 1);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Mario Rossi', $fields['lead']['value']);
    }

    public function test_refinement_the_first_one_resolves_to_first_candidate(): void
    {
        $user = $this->actingAsUser();

        $interpret  = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        // Candidates are sorted by confidence desc: [Rossi SRL (0.8), Mario Rossi (0.65), Studio Rossi (0.65)]
        // "The first one" → index 0 → Rossi SRL (id: 7)
        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'The first one']);

        $response->assertStatus(200)
            ->assertJsonPath('data.ambiguities.0.selected_candidate_id', 7);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Rossi SRL', $fields['lead']['value']);
    }

    // --- refinement: partial name ---

    public function test_refinement_mario_resolves_to_mario_rossi(): void
    {
        $user = $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Mario']);

        $response->assertStatus(200)
            ->assertJsonPath('data.ambiguities.0.selected_candidate_id', 1);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Mario Rossi', $fields['lead']['value']);
    }

    // --- refinement: type resolution (ambiguous) ---

    public function test_refinement_the_company_does_not_resolve_when_multiple_companies(): void
    {
        $user = $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'The company']);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.ambiguities.0.selected_candidate_id', null);

        $warnings = $response->json('data.warnings');
        $this->assertNotEmpty($warnings);
    }

    // --- status after resolution ---

    public function test_resolving_lead_ambiguity_alone_keeps_proposal_draft(): void
    {
        $user = $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Rossi SRL']);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_full_flow_call_rossi_to_ready(): void
    {
        $user = $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Rossi SRL'])
            ->assertJsonPath('data.status', 'draft');

        $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Tomorrow morning'])
            ->assertJsonPath('data.status', 'ready');
    }

    // --- last_refinement metadata on resolution ---

    public function test_refinement_resolution_sets_last_refinement_with_lead_change(): void
    {
        $user = $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Rossi SRL']);

        $response->assertStatus(200);

        $changes = collect($response->json('data.last_refinement.changes'))->keyBy('field');
        $this->assertArrayHasKey('lead', $changes->all());
        $this->assertNull($changes['lead']['from']);
        $this->assertEquals('Rossi SRL', $changes['lead']['to']);
    }

    public function test_refinement_resolution_summary_says_lead_resolved(): void
    {
        $user = $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Rossi SRL']);

        $response->assertStatus(200)
            ->assertJsonPath('data.last_refinement.summary', 'Lead resolved.');
    }

    // --- tomorrow morning still works on resolved proposal ---

    public function test_tomorrow_morning_on_ambiguous_proposal_does_not_resolve_date(): void
    {
        $user = $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        // Proposal has unresolved lead ambiguity — "tomorrow morning" should resolve date/time
        // but status stays draft because blocking ambiguity remains.
        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Tomorrow morning']);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'draft');

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals(now()->addDay()->toDateString(), $fields['date']['value']);
        $this->assertEquals('09:00', $fields['time']['value']);
    }
}
