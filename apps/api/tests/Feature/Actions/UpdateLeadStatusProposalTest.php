<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsDemoLeads;
use Tests\TestCase;

/**
 * update_lead_status — proposal-time interpretation and readiness.
 *
 * The proposal is ready only when the target lead is uniquely resolved (via the
 * DB-backed LeadEntityResolver) AND a valid target lifecycle state is known.
 * Unknown/ambiguous lead and missing state all keep it draft.
 */
class UpdateLeadStatusProposalTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoLeads;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    // ── Intent recognition ────────────────────────────────────────────────────

    public function test_mark_as_contacted_produces_update_lead_status_intent(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/actions/interpret', ['text' => 'Mark Rossini as contacted'])
            ->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_lead_status');
    }

    public function test_set_to_qualified_produces_update_lead_status_intent(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/actions/interpret', ['text' => 'Set Rossini to qualified'])
            ->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_lead_status');
    }

    public function test_qualify_verb_produces_update_lead_status_intent(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/actions/interpret', ['text' => 'Qualify Rossini'])
            ->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_lead_status');
    }

    // ── Known lead + valid state → ready ──────────────────────────────────────

    public function test_known_lead_and_valid_state_is_ready(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Mark Rossini as contacted']);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_lead_status')
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.ambiguities', []);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Rossini', $fields['lead']['value']);
        $this->assertEquals('contacted', $fields['state']['value']);
    }

    public function test_ready_proposal_has_changes_with_correct_module_and_operation(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Set Rossini to won']);

        $changes = $response->json('data.changes');
        $this->assertNotEmpty($changes);
        $this->assertEquals('leads', $changes[0]['module']);
        $this->assertEquals('update', $changes[0]['type']);
    }

    public function test_move_to_won_is_normalized(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Move Rossini to won']);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('won', $fields['state']['value']);
    }

    // ── Unknown lead → draft (missing) ────────────────────────────────────────

    public function test_unknown_lead_is_not_ready(): void
    {
        // No fixture lead matches 'Ghosty'.
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Mark Ghosty as contacted']);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_lead_status')
            ->assertJsonPath('data.status', 'draft');

        $missingKeys = collect($response->json('data.missing'))->pluck('key')->all();
        $this->assertContains('lead', $missingKeys);
    }

    // ── Ambiguous lead → draft with blocking ambiguity ────────────────────────

    public function test_ambiguous_lead_is_not_ready_and_exposes_blocking_ambiguity(): void
    {
        // 'Rossi' matches multiple fixtures (Rossi SRL / Mario Rossi / Studio Rossi).
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Mark Rossi as contacted']);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_lead_status')
            ->assertJsonPath('data.status', 'draft');

        $missingKeys = collect($response->json('data.missing'))->pluck('key')->all();
        $this->assertNotContains('lead', $missingKeys);

        $ambiguities = $response->json('data.ambiguities');
        $this->assertNotEmpty($ambiguities);
        $leadAmbiguity = collect($ambiguities)->firstWhere('key', 'lead');
        $this->assertNotNull($leadAmbiguity);
        $this->assertTrue($leadAmbiguity['blocking']);
        $this->assertNull($leadAmbiguity['selected_candidate_id']);
        $this->assertGreaterThanOrEqual(2, count($leadAmbiguity['candidates']));
    }

    // ── Missing state → draft ─────────────────────────────────────────────────

    public function test_missing_state_is_not_ready(): void
    {
        $this->actingAsUser();

        // Explicit update verb with no recognizable target lifecycle state.
        $response = $this->postJson('/api/actions/interpret', ['text' => 'Update the Rossini lead']);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_lead_status')
            ->assertJsonPath('data.status', 'draft');

        $missingKeys = collect($response->json('data.missing'))->pluck('key')->all();
        $this->assertContains('state', $missingKeys);
        $this->assertNotContains('lead', $missingKeys);
    }

    // ── Canonical phrase ──────────────────────────────────────────────────────

    public function test_ready_proposal_exposes_canonical_phrase(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Mark Rossini as contacted']);

        $response->assertStatus(200)
            ->assertJsonPath('data.canonical_phrase', 'Set lead Rossini to contacted.');
    }

    public function test_draft_proposal_has_no_canonical_phrase(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Mark Ghosty as contacted']);

        $response->assertStatus(200)
            ->assertJsonPath('data.canonical_phrase', null);
    }

    // ── Provider blindness: no ids leaked into proposal entities ──────────────

    public function test_proposal_entities_carry_no_lead_id(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Mark Rossini as contacted']);

        $entities = $response->json('data.entities');
        $this->assertArrayNotHasKey('lead_id', $entities);
        $this->assertArrayNotHasKey('lead_query', $entities);
    }

    // ── Existing intents remain unaffected ────────────────────────────────────

    public function test_assign_lead_intent_is_unchanged(): void
    {
        User::factory()->create(['name' => 'Marco']);
        $this->actingAsUser();

        $this->postJson('/api/actions/interpret', ['text' => 'Assign Rossini to Marco'])
            ->assertStatus(200)
            ->assertJsonPath('data.intent', 'assign_lead');
    }

    public function test_create_task_intent_is_unchanged(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/actions/interpret', ['text' => 'Create a task for Rossini'])
            ->assertStatus(200)
            ->assertJsonPath('data.intent', 'create_task');
    }

    public function test_update_task_status_intent_is_unchanged(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/actions/interpret', ['text' => 'Mark the Follow-up task as completed'])
            ->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_task_status');
    }
}
