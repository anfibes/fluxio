<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Fluxio\Actions\Contracts\CommandInterpreterInterface;
use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Leads\Models\Lead;
use Fluxio\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Identity continuity (slice 1) — persistence contract of `resolved_entities`:
 *
 *   null = legacy proposal (pre-contract), [] = contract proposal with no resolved
 *   identity, map = server-owned identities keyed by operational role.
 *
 * Identity is written ONLY by the two resolution authorities (auto-resolution in
 * ActionInterpreterService, ambiguity resolution in ActionProposalRefinementService)
 * and never derives from provider input. It is internal state: not exposed through
 * the public API resource in this slice.
 */
class ResolvedEntityPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    /** Three leads matching "Rossi" — same shape as the canonical demo set, unpinned ids. */
    private function seedAmbiguousRossiLeads(): void
    {
        Lead::factory()->create(['name' => 'Rossi SRL', 'company' => 'Rossi SRL', 'status' => 'new']);
        Lead::factory()->create(['name' => 'Mario Rossi', 'company' => null, 'status' => 'new']);
        Lead::factory()->create(['name' => 'Studio Rossi', 'company' => 'Studio Rossi', 'status' => 'new']);
    }

    // ── Auto-resolution ──────────────────────────────────────────────────────

    public function test_auto_resolved_lead_persists_server_owned_identity(): void
    {
        // A decoy first so the expected lead's PK is not a fixed low id.
        Lead::factory()->create(['name' => 'Acme Widgets', 'company' => 'Acme Widgets']);
        $rossini = Lead::factory()->create(['name' => 'Rossini', 'company' => 'Rossini', 'status' => 'new']);
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Mark Rossini as qualified']);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_lead_status')
            ->assertJsonPath('data.status', 'ready');

        $proposal = ActionProposal::findOrFail($response->json('data.id'));

        // The resolver's real primary key survives auto-resolution.
        $this->assertSame($rossini->id, $proposal->resolved_entities['lead']['id'] ?? null);
        $this->assertSame('company', $proposal->resolved_entities['lead']['type'] ?? null);
        $this->assertSame('Rossini', $proposal->resolved_entities['lead']['label'] ?? null);
    }

    // ── Ambiguity lifecycle ──────────────────────────────────────────────────

    public function test_identity_is_written_only_on_explicit_selection(): void
    {
        $this->seedAmbiguousRossiLeads();
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Mark Rossi as qualified']);
        $interpret->assertStatus(200)->assertJsonPath('data.status', 'draft');

        $proposalId = $interpret->json('data.id');

        // Before any selection: contract-bearing but identity-free.
        $this->assertSame([], ActionProposal::findOrFail($proposalId)->resolved_entities);

        // Narrowing ("the company" still matches two) writes no identity.
        $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'the company'])
            ->assertStatus(200)
            ->assertJsonPath('data.last_refinement.ambiguity_outcome.kind', 'narrowed');
        $this->assertSame([], ActionProposal::findOrFail($proposalId)->resolved_entities);

        // An unresolved selector (out-of-range ordinal) writes no identity either.
        $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'the third one'])
            ->assertStatus(200)
            ->assertJsonPath('data.last_refinement.ambiguity_outcome.kind', 'unresolved');
        $this->assertSame([], ActionProposal::findOrFail($proposalId)->resolved_entities);

        // Explicit selection persists the identity of exactly that candidate.
        $resolve = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'the first one']);
        $resolve->assertStatus(200)
            ->assertJsonPath('data.last_refinement.ambiguity_outcome.kind', 'resolved');

        $proposal = ActionProposal::findOrFail($proposalId);
        $selectedId = $resolve->json('data.ambiguities.0.selected_candidate_id');

        $this->assertNotNull($selectedId);
        // Invariant: both identity surfaces name the same candidate.
        $this->assertSame($selectedId, $proposal->resolved_entities['lead']['id'] ?? null);
        $this->assertSame(
            Lead::findOrFail($selectedId)->name,
            $proposal->resolved_entities['lead']['label'] ?? null,
        );
    }

    // ── Task identity (update_task_status — slice 2) ─────────────────────────

    public function test_auto_resolved_task_persists_server_owned_identity(): void
    {
        Task::factory()->create(['title' => 'Unrelated chores', 'status' => 'pending']);
        $target = Task::factory()->create(['title' => 'Prepare quote Rossini', 'status' => 'pending']);
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Mark the Prepare quote Rossini task as completed']);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'update_task_status')
            ->assertJsonPath('data.status', 'ready');

        $proposal = ActionProposal::findOrFail($response->json('data.id'));

        $this->assertSame($target->id, $proposal->resolved_entities['task']['id'] ?? null);
        $this->assertSame('task', $proposal->resolved_entities['task']['type'] ?? null);
        $this->assertSame('Prepare quote Rossini', $proposal->resolved_entities['task']['label'] ?? null);
    }

    public function test_ambiguous_task_selection_persists_matching_identity(): void
    {
        Task::factory()->count(2)->create(['title' => 'Follow-up', 'status' => 'pending']);
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Mark the Follow-up task as completed']);
        $interpret->assertStatus(200)->assertJsonPath('data.status', 'draft');

        $proposalId = $interpret->json('data.id');

        // Contract-bearing but identity-free until the user selects.
        $this->assertSame([], ActionProposal::findOrFail($proposalId)->resolved_entities);

        $resolve = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'the second one']);
        $resolve->assertStatus(200)
            ->assertJsonPath('data.last_refinement.ambiguity_outcome.kind', 'resolved');

        $proposal = ActionProposal::findOrFail($proposalId);
        $selectedId = $resolve->json('data.ambiguities.0.selected_candidate_id');

        // Invariant: both identity surfaces name the same selected candidate.
        $this->assertNotNull($selectedId);
        $this->assertSame($selectedId, $proposal->resolved_entities['task']['id'] ?? null);
        $this->assertSame(
            $interpret->json('data.ambiguities.0.candidates.1.id'),
            $proposal->resolved_entities['task']['id'] ?? null,
        );
    }

    // ── Contract marker: [] vs null ──────────────────────────────────────────

    public function test_new_proposal_without_resolved_target_persists_empty_map_not_null(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Create a task']);
        $response->assertStatus(200);

        $proposal = ActionProposal::findOrFail($response->json('data.id'));

        // [] (contract-bearing, nothing resolved) — never null (legacy marker).
        $this->assertSame([], $proposal->resolved_entities);
        $this->assertNotNull($proposal->resolved_entities);
    }

    // ── Provider blindness guardrail ─────────────────────────────────────────

    public function test_provider_id_like_entities_cannot_populate_resolved_entities(): void
    {
        $rossini = Lead::factory()->create(['name' => 'Rossini', 'company' => 'Rossini', 'status' => 'new']);
        $this->actingAsUser();

        // A hostile/buggy provider emitting id-like entity keys. The runtime must
        // derive identity exclusively from its own resolver, never from these.
        $this->app->bind(CommandInterpreterInterface::class, fn () => new class implements CommandInterpreterInterface
        {
            public function interpret(string $text): NormalizedCommand
            {
                return new NormalizedCommand(
                    intent: 'update_lead_status',
                    confidence: 0.9,
                    sourceText: $text,
                    locale: 'en',
                    entities: [
                        'lead_query' => 'Rossini',
                        'state' => 'qualified',
                        'lead_id' => 999999,
                        'selected_candidate_id' => 888888,
                        'id' => 777777,
                    ],
                );
            }
        });

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Mark Rossini as qualified']);
        $response->assertStatus(200);

        $proposal = ActionProposal::findOrFail($response->json('data.id'));

        // Identity is the resolver's PK — the provider-supplied numbers appear
        // nowhere in resolved_entities.
        $this->assertSame($rossini->id, $proposal->resolved_entities['lead']['id'] ?? null);
        $this->assertSame(['lead'], array_keys($proposal->resolved_entities));
        $encoded = json_encode($proposal->resolved_entities);
        $this->assertStringNotContainsString('999999', $encoded);
        $this->assertStringNotContainsString('888888', $encoded);
        $this->assertStringNotContainsString('777777', $encoded);
    }

    // ── API surface ──────────────────────────────────────────────────────────

    public function test_resolved_entities_is_not_exposed_in_public_api_responses(): void
    {
        Lead::factory()->create(['name' => 'Rossini', 'company' => 'Rossini', 'status' => 'new']);
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Mark Rossini as qualified']);
        $interpret->assertStatus(200);
        $this->assertArrayNotHasKey('resolved_entities', $interpret->json('data'));

        $proposalId = $interpret->json('data.id');

        $refine = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'as contacted']);
        $refine->assertStatus(200);
        $this->assertArrayNotHasKey('resolved_entities', $refine->json('data'));
    }
}
