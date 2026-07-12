<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Leads\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Identity continuity (slice 1) — update_lead_status is the first executor that
 * consumes `resolved_entities`: the lead selected or auto-resolved on the proposal
 * is exactly the row the executor updates, by primary key, label never compared.
 *
 * Legacy proposals (resolved_entities === null) keep the previous textual
 * re-resolution; contract proposals ([] or map) never fall back to labels.
 */
class ExecuteUpdateLeadStatusIdentityContinuityTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * A confirmed update_lead_status proposal under the identity-continuity
     * contract. $resolvedEntities: null (legacy), [] (contract, no identity),
     * or a role-keyed identity map.
     */
    private function confirmedProposal(User $owner, string $leadLabel, string $state, ?array $resolvedEntities): ActionProposal
    {
        return ActionProposal::create([
            'user_id' => $owner->id,
            'intent' => 'update_lead_status',
            'status' => 'confirmed',
            'confidence' => 0.85,
            'source_text' => "Mark {$leadLabel} as {$state}",
            'entities' => ['lead' => $leadLabel, 'state' => $state],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [
                ['key' => 'lead',  'label' => 'Lead',   'value' => $leadLabel, 'source' => 'detected', 'required' => true],
                ['key' => 'state', 'label' => 'Status', 'value' => $state,     'source' => 'detected', 'required' => true],
            ],
            'changes' => [
                ['type' => 'update', 'label' => 'Update Lead Status', 'module' => 'leads', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [],
            'resolved_entities' => $resolvedEntities,
        ]);
    }

    // ── Auto-resolved lead → execution by primary key ────────────────────────

    public function test_auto_resolved_lead_updates_the_exact_record_by_id(): void
    {
        Lead::factory()->create(['name' => 'Acme Widgets', 'company' => 'Acme Widgets', 'status' => 'new']);
        $rossini = Lead::factory()->create(['name' => 'Rossini', 'company' => 'Rossini', 'status' => 'new']);
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Mark Rossini as qualified']);
        $interpret->assertStatus(200)->assertJsonPath('data.status', 'ready');

        $proposalId = $interpret->json('data.id');
        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);
        $this->postJson("/api/actions/{$proposalId}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('leads', ['id' => $rossini->id, 'status' => 'qualified']);
    }

    // ── Homonyms: the user's selection is what executes ──────────────────────

    public function test_homonymous_leads_execute_against_the_selected_candidate(): void
    {
        // Two leads with the SAME label — textual re-resolution could never
        // distinguish them (it used to 422 forever on this scenario).
        $first = Lead::factory()->create(['name' => 'Rossi', 'company' => null, 'status' => 'new']);
        $second = Lead::factory()->create(['name' => 'Rossi', 'company' => null, 'status' => 'new']);
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Mark Rossi as qualified']);
        $interpret->assertStatus(200)->assertJsonPath('data.status', 'draft');

        $candidates = $interpret->json('data.ambiguities.0.candidates');
        $this->assertCount(2, $candidates);
        $selectedId = $candidates[1]['id'];
        $this->assertSame($second->id, $selectedId);

        $proposalId = $interpret->json('data.id');

        // Deterministic ordinal selection of the second homonym.
        $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'the second one'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.last_refinement.ambiguity_outcome.kind', 'resolved');

        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);

        // The duplicated label must not block execution: the executor acts by id.
        $this->postJson("/api/actions/{$proposalId}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('leads', ['id' => $second->id, 'status' => 'qualified']);
        $this->assertDatabaseHas('leads', ['id' => $first->id, 'status' => 'new']);
    }

    // ── Rename after confirmation: same identity, same row ───────────────────

    public function test_renamed_lead_still_updates_the_same_record(): void
    {
        $rossini = Lead::factory()->create(['name' => 'Rossini', 'company' => 'Rossini', 'status' => 'new']);
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Mark Rossini as qualified']);
        $interpret->assertStatus(200)->assertJsonPath('data.status', 'ready');

        $proposalId = $interpret->json('data.id');
        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);

        // Rename between confirmation and execution — the label snapshot goes stale,
        // the identity does not.
        $rossini->update(['name' => 'Rossini Rebranded', 'company' => 'Rossini Rebranded']);

        $this->postJson("/api/actions/{$proposalId}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('leads', [
            'id' => $rossini->id,
            'name' => 'Rossini Rebranded',
            'status' => 'qualified',
        ]);
    }

    // ── Delete after confirmation: safe 422, proposal stays confirmed ────────

    public function test_deleted_lead_fails_safely_and_touches_nothing(): void
    {
        $bystander = Lead::factory()->create(['name' => 'Bystander', 'company' => null, 'status' => 'new']);
        $rossini = Lead::factory()->create(['name' => 'Rossini', 'company' => 'Rossini', 'status' => 'new']);
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Mark Rossini as qualified']);
        $interpret->assertStatus(200)->assertJsonPath('data.status', 'ready');

        $proposalId = $interpret->json('data.id');
        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);

        $rossini->delete();

        $this->postJson("/api/actions/{$proposalId}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['lead']]);

        // Current failure semantics preserved: confirmed, no failure recorded.
        $refreshed = ActionProposal::findOrFail($proposalId);
        $this->assertSame('confirmed', $refreshed->status);
        $this->assertNull($refreshed->failed_at);

        // No other lead was harmed by a fallback lookup.
        $this->assertDatabaseHas('leads', ['id' => $bystander->id, 'status' => 'new']);
    }

    // ── Contract proposal without identity: no textual fallback ──────────────

    public function test_contract_proposal_without_identity_does_not_fall_back_to_label(): void
    {
        $rossini = Lead::factory()->create(['name' => 'Rossini', 'company' => 'Rossini', 'status' => 'new']);
        $actor = $this->actingAsUser();

        // [] = built under the contract, lead never resolved. The label points at
        // an existing row, but label re-resolution is reserved for legacy (null).
        $proposal = $this->confirmedProposal($actor, 'Rossini', 'qualified', []);

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['lead']]);

        $this->assertDatabaseHas('leads', ['id' => $rossini->id, 'status' => 'new']);
        $this->assertSame('confirmed', ActionProposal::findOrFail($proposal->id)->status);
    }

    public function test_malformed_identity_entry_is_not_treated_as_legacy(): void
    {
        $rossini = Lead::factory()->create(['name' => 'Rossini', 'company' => 'Rossini', 'status' => 'new']);
        $actor = $this->actingAsUser();

        // Structure without an id: invalid identity, NOT a legacy marker — must
        // fail safely instead of silently re-resolving the label.
        $proposal = $this->confirmedProposal($actor, 'Rossini', 'qualified', [
            'lead' => ['type' => 'company', 'label' => 'Rossini'],
        ]);

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['lead']]);

        $this->assertDatabaseHas('leads', ['id' => $rossini->id, 'status' => 'new']);
    }

    // ── Identity is the only lookup key: the label is never consulted ────────

    public function test_execution_follows_the_identity_not_the_label(): void
    {
        $target = Lead::factory()->create(['name' => 'Alpha', 'company' => null, 'status' => 'new']);
        $decoy = Lead::factory()->create(['name' => 'Beta', 'company' => null, 'status' => 'new']);
        $actor = $this->actingAsUser();

        // Incoherent fixture: identity points at Alpha, label says Beta. The id is
        // the identity authority; the label is presentation-only and must not
        // redirect execution to a different row.
        $proposal = $this->confirmedProposal($actor, 'Beta', 'qualified', [
            'lead' => ['id' => $target->id, 'type' => 'person', 'label' => 'Alpha'],
        ]);

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('leads', ['id' => $target->id, 'status' => 'qualified']);
        $this->assertDatabaseHas('leads', ['id' => $decoy->id, 'status' => 'new']);
    }

    // ── Legacy proposal (null): textual fallback preserved ───────────────────

    public function test_legacy_proposal_with_null_resolved_entities_uses_textual_fallback(): void
    {
        $rossini = Lead::factory()->create(['name' => 'Rossini', 'company' => 'Rossini', 'status' => 'new']);
        $actor = $this->actingAsUser();

        // EXPLICIT LEGACY FIXTURE: resolved_entities === null simulates a proposal
        // persisted before the identity-continuity contract. This test exercises
        // the legacy label re-resolution branch (resolveLeadByLabelLegacy) and can
        // be deleted together with that branch.
        $proposal = $this->confirmedProposal($actor, 'Rossini', 'qualified', null);

        $this->assertNull(ActionProposal::findOrFail($proposal->id)->resolved_entities);

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('leads', ['id' => $rossini->id, 'status' => 'qualified']);
    }
}
