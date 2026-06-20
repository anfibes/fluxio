<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsDemoLeads;
use Tests\TestCase;

/**
 * Regression: create_task can generate a blocking lead ambiguity, so it MUST be
 * able to resolve it via refinement — otherwise the proposal becomes permanently
 * unconfirmable (draft → blocking ambiguity → cannot resolve → cannot confirm).
 */
class CreateTaskAmbiguityResolutionTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoLeads;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * @return array{id: string, candidates: array<int, array<string, mixed>>}
     */
    private function ambiguousCreateTask(): array
    {
        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Create a high priority task for Rossi tomorrow at 10',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'create_task')
            ->assertJsonPath('data.status', 'draft');

        $ambiguities = $response->json('data.ambiguities');
        $this->assertNotEmpty($ambiguities, 'Expected a blocking lead ambiguity for create_task.');
        $this->assertEquals('lead', $ambiguities[0]['key']);
        $this->assertTrue($ambiguities[0]['blocking']);
        $this->assertNull($ambiguities[0]['selected_candidate_id']);

        return [
            'id' => $response->json('data.id'),
            'candidates' => $ambiguities[0]['candidates'],
        ];
    }

    // 1. Capability exposure ----------------------------------------------------

    public function test_create_task_with_ambiguous_lead_exposes_ambiguity_resolution_capability(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Create a high priority task for Rossi tomorrow at 10',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'create_task')
            ->assertJsonPath('data.capabilities.supports_ambiguity_resolution', true);

        $refinements = collect($response->json('data.capabilities.refinements'))->pluck('key')->all();
        $this->assertContains('resolve_ambiguity', $refinements);

        // The proposal really does carry a blocking lead ambiguity.
        $this->assertTrue($response->json('data.ambiguities.0.blocking'));
    }

    // 2. Resolution via refinement ---------------------------------------------

    public function test_create_task_blocking_lead_ambiguity_can_be_resolved_by_refinement(): void
    {
        $this->actingAsUser();

        $proposal = $this->ambiguousCreateTask();

        // "the person" matches exactly one candidate (Mario Rossi) → resolved.
        $response = $this->postJson("/api/actions/{$proposal['id']}/refine", ['text' => 'the person']);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $proposal['id'])
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.last_refinement.ambiguity_outcome.kind', 'resolved');

        // Ambiguity is resolved: a candidate id is selected and no blocking ambiguity remains unresolved.
        $ambiguities = $response->json('data.ambiguities');
        foreach ($ambiguities as $ambiguity) {
            if (($ambiguity['blocking'] ?? false) === true) {
                $this->assertNotNull(
                    $ambiguity['selected_candidate_id'],
                    'A blocking ambiguity must carry a selected candidate after resolution.',
                );
            }
        }
        $this->assertEquals('lead', $ambiguities[0]['key']);
        $this->assertNotNull($ambiguities[0]['selected_candidate_id']);

        // Lead field and entities are updated consistently.
        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Mario Rossi', $fields['lead']['value']);
        $this->assertEquals('Mario Rossi', $response->json('data.entities.lead'));

        // And the resolved proposal can be confirmed + executed.
        $this->postJson("/api/actions/{$proposal['id']}/confirm")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'confirmed');

        $this->postJson("/api/actions/{$proposal['id']}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');
    }

    public function test_create_task_lead_ambiguity_narrows_then_resolves_by_ordinal(): void
    {
        $this->actingAsUser();

        $proposal = $this->ambiguousCreateTask();

        // "the company" matches two candidates (Rossi SRL, Studio Rossi) → narrowed, still blocking.
        $narrow = $this->postJson("/api/actions/{$proposal['id']}/refine", ['text' => 'the company']);
        $narrow->assertStatus(200)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.last_refinement.ambiguity_outcome.kind', 'narrowed');

        $this->assertCount(2, $narrow->json('data.ambiguities.0.candidates'));

        // A subsequent ordinal operates on the narrowed set and resolves.
        $resolve = $this->postJson("/api/actions/{$proposal['id']}/refine", ['text' => 'the first one']);
        $resolve->assertStatus(200)
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.last_refinement.ambiguity_outcome.kind', 'resolved');

        $this->assertNotNull($resolve->json('data.ambiguities.0.selected_candidate_id'));
    }

    // 3. No unrelated capabilities enabled -------------------------------------

    public function test_create_task_ambiguity_resolution_does_not_enable_unrelated_capabilities(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Create a high priority task for Rossi tomorrow at 10',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.capabilities.supports_ambiguity_resolution', true)
            ->assertJsonPath('data.capabilities.supports_collection_mutations', false)
            ->assertJsonPath('data.capabilities.supports_contextual_references', false);

        // Only the intended refinement capability was added; collection refinements stay absent.
        $refinements = collect($response->json('data.capabilities.refinements'))->pluck('key')->all();
        $this->assertContains('resolve_ambiguity', $refinements);
        $this->assertContains('replace_field', $refinements);
        $this->assertContains('clear_field', $refinements);
        $this->assertNotContains('append_collection_item', $refinements);
        $this->assertNotContains('remove_collection_item', $refinements);
        $this->assertNotContains('replace_collection_item', $refinements);
        $this->assertNotContains('contextual_reference', $refinements);
    }
}
