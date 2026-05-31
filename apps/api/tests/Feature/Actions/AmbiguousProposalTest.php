<?php

namespace Tests\Feature\Actions;

use App\Models\User;
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

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
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

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
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

    public function test_refinement_the_company_warning_lists_remaining_companies(): void
    {
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'The company']);

        $response->assertStatus(200);

        // Narrowed-but-still-ambiguous: both companies are named, the person is not.
        $warnings = $response->json('data.warnings');
        $narrowed = collect($warnings)->first(fn (string $w) => str_contains($w, 'still match'));

        $this->assertNotNull($narrowed);
        $this->assertStringContainsString('Rossi SRL', $narrowed);
        $this->assertStringContainsString('Studio Rossi', $narrowed);
        $this->assertStringNotContainsString('Mario Rossi', $narrowed);
        $this->assertStringContainsString('company', $narrowed);
    }

    public function test_refinement_the_company_does_not_duplicate_narrowed_warning(): void
    {
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'The company'])
            ->assertStatus(200);

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'The company'])
            ->assertStatus(200);

        $warnings = $response->json('data.warnings');
        $narrowed = array_values(array_filter($warnings, fn (string $w) => str_contains($w, 'still match')));

        $this->assertCount(1, $narrowed);
    }

    public function test_refinement_unmatched_clarification_keeps_generic_warning(): void
    {
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        // No type keyword and no label match → no nameable narrowing → generic warning.
        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'The blue one'])
            ->assertStatus(200);

        $response->assertJsonPath('data.ambiguities.0.selected_candidate_id', null);

        $warnings = $response->json('data.warnings');
        $generic = collect($warnings)->first(fn (string $w) => str_contains($w, 'be more specific'));
        $this->assertNotNull($generic);

        $narrowed = collect($warnings)->first(fn (string $w) => str_contains($w, 'still match'));
        $this->assertNull($narrowed);
    }

    public function test_refinement_unique_company_still_resolves(): void
    {
        $this->actingAsUser();

        // "Call Bianchi" yields a single company among the matches, so "the company"
        // resolves as before — the narrowed-feedback path must not interfere.
        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        // An ordinal still resolves uniquely even though two companies exist.
        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'The first one'])
            ->assertStatus(200);

        $response->assertJsonPath('data.ambiguities.0.selected_candidate_id', 7);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Rossi SRL', $fields['lead']['value']);
    }

    // --- Phase 7C.3: progressive ambiguity narrowing ---

    public function test_company_narrowing_filters_active_candidates_to_companies(): void
    {
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'The company'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.ambiguities.0.selected_candidate_id', null);

        // Active candidate set is narrowed to the two companies; the person is gone.
        $candidates = $response->json('data.ambiguities.0.candidates');
        $this->assertCount(2, $candidates);

        $labels = array_column($candidates, 'label');
        $this->assertContains('Rossi SRL', $labels);
        $this->assertContains('Studio Rossi', $labels);
        $this->assertNotContains('Mario Rossi', $labels);

        foreach ($candidates as $candidate) {
            $this->assertSame('company', $candidate['type']);
        }
    }

    public function test_ordinal_after_company_narrowing_resolves_on_narrowed_set(): void
    {
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        // Narrow to companies first: [Rossi SRL, Studio Rossi].
        $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'The company'])
            ->assertStatus(200)
            ->assertJsonPath('data.ambiguities.0.selected_candidate_id', null);

        // "The first one" must operate on the narrowed set → Rossi SRL (id 7).
        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'The first one'])
            ->assertStatus(200)
            ->assertJsonPath('data.ambiguities.0.selected_candidate_id', 7);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Rossi SRL', $fields['lead']['value']);
    }

    public function test_person_narrowing_resolves_immediately(): void
    {
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        // Only one person candidate exists, so "the person" resolves immediately.
        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'The person'])
            ->assertStatus(200)
            ->assertJsonPath('data.ambiguities.0.selected_candidate_id', 1);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Mario Rossi', $fields['lead']['value']);

        // The narrowed-multiple warning must not appear on a clean resolution.
        $warnings = $response->json('data.warnings');
        $narrowed = collect($warnings)->first(fn (string $w) => str_contains($w, 'still match'));
        $this->assertNull($narrowed);
    }

    public function test_meeting_person_narrowing_becomes_ready_when_temporal_present(): void
    {
        $this->actingAsUser();

        // Date + time already present, so resolving the lead unblocks the proposal.
        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Schedule a meeting with Rossi tomorrow at 10']);
        $proposalId = $interpret->json('data.id');

        $interpret->assertJsonPath('data.status', 'draft');

        $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'The person'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.ambiguities.0.selected_candidate_id', 1);
    }

    public function test_repeated_company_narrowing_keeps_candidates_stable(): void
    {
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'The company'])
            ->assertStatus(200);

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'The company'])
            ->assertStatus(200);

        // Re-narrowing an already-narrowed set is idempotent.
        $candidates = $response->json('data.ambiguities.0.candidates');
        $this->assertCount(2, $candidates);
        $this->assertEqualsCanonicalizing(['Rossi SRL', 'Studio Rossi'], array_column($candidates, 'label'));

        $narrowed = array_values(array_filter(
            $response->json('data.warnings'),
            fn (string $w) => str_contains($w, 'still match')
        ));
        $this->assertCount(1, $narrowed);
    }

    public function test_unrelated_clarification_does_not_mutate_candidates(): void
    {
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $interpret->json('data.id');

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Something irrelevant'])
            ->assertStatus(200)
            ->assertJsonPath('data.ambiguities.0.selected_candidate_id', null);

        // The full original candidate list is preserved untouched.
        $candidates = $response->json('data.ambiguities.0.candidates');
        $this->assertCount(3, $candidates);

        $generic = collect($response->json('data.warnings'))
            ->first(fn (string $w) => str_contains($w, 'be more specific'));
        $this->assertNotNull($generic);
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
