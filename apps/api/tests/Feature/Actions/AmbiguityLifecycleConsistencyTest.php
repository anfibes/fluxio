<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 9C.1 — Ambiguity lifecycle consistency.
 *
 * The authoritative ambiguity state must be self-consistent: a resolved ambiguity
 * (selected_candidate_id !== null) must NOT remain blocking, and an unresolved one
 * (selected_candidate_id === null) must stay blocking. No consumer should need the
 * hidden `blocking && selected_candidate_id === null` rule to interpret the payload.
 */
class AmbiguityLifecycleConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Runtime tripwire: for every ambiguity, a non-null selected_candidate_id implies
     * blocking === false (and a blocking ambiguity implies an unresolved candidate).
     *
     * @param  array<int, array<string, mixed>>|null  $ambiguities
     */
    private function assertAmbiguityLifecycleInvariant(?array $ambiguities): void
    {
        foreach ($ambiguities ?? [] as $i => $ambiguity) {
            if (($ambiguity['selected_candidate_id'] ?? null) !== null) {
                $this->assertFalse(
                    $ambiguity['blocking'] ?? false,
                    "ambiguities[{$i}] is resolved (selected_candidate_id set) but still blocking.",
                );
            }

            if (($ambiguity['blocking'] ?? false) === true) {
                $this->assertNull(
                    $ambiguity['selected_candidate_id'] ?? null,
                    "ambiguities[{$i}] is blocking but already carries a selected_candidate_id.",
                );
            }
        }
    }

    private function interpretAmbiguous(): string
    {
        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Schedule a meeting with Rossi tomorrow morning',
        ]);

        $response->assertStatus(200)->assertJsonPath('data.status', 'draft');
        $this->assertAmbiguityLifecycleInvariant($response->json('data.ambiguities'));

        return $response->json('data.id');
    }

    // 1. Unresolved blocking ambiguity -----------------------------------------

    public function test_unresolved_ambiguity_is_blocking_with_null_candidate(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Schedule a meeting with Rossi tomorrow morning',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.ambiguities.0.blocking', true)
            ->assertJsonPath('data.ambiguities.0.selected_candidate_id', null);

        $this->assertAmbiguityLifecycleInvariant($response->json('data.ambiguities'));
    }

    // 2. Resolved ambiguity ----------------------------------------------------

    public function test_resolved_ambiguity_is_not_blocking_with_selected_candidate(): void
    {
        $this->actingAsUser();

        $id = $this->interpretAmbiguous();

        $response = $this->postJson("/api/actions/{$id}/refine", ['text' => 'the person']);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.last_refinement.ambiguity_outcome.kind', 'resolved')
            ->assertJsonPath('data.ambiguities.0.blocking', false);

        $this->assertNotNull($response->json('data.ambiguities.0.selected_candidate_id'));
        $this->assertAmbiguityLifecycleInvariant($response->json('data.ambiguities'));
    }

    // 3. Narrowed ambiguity stays blocking -------------------------------------

    public function test_narrowed_ambiguity_remains_blocking(): void
    {
        $this->actingAsUser();

        $id = $this->interpretAmbiguous();

        $response = $this->postJson("/api/actions/{$id}/refine", ['text' => 'the company']);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.last_refinement.ambiguity_outcome.kind', 'narrowed')
            ->assertJsonPath('data.ambiguities.0.blocking', true)
            ->assertJsonPath('data.ambiguities.0.selected_candidate_id', null);

        $this->assertAmbiguityLifecycleInvariant($response->json('data.ambiguities'));
    }

    // 4. Full lifecycle unchanged + invariant holds at every step --------------

    public function test_lifecycle_invariant_holds_through_resolve_confirm_execute(): void
    {
        $this->actingAsUser();

        $id = $this->interpretAmbiguous();

        $resolve = $this->postJson("/api/actions/{$id}/refine", ['text' => 'the person']);
        $resolve->assertStatus(200)->assertJsonPath('data.status', 'ready');
        $this->assertAmbiguityLifecycleInvariant($resolve->json('data.ambiguities'));

        $confirm = $this->postJson("/api/actions/{$id}/confirm");
        $confirm->assertStatus(200)->assertJsonPath('data.status', 'confirmed');
        $this->assertAmbiguityLifecycleInvariant($confirm->json('data.ambiguities'));

        $execute = $this->postJson("/api/actions/{$id}/execute");
        $execute->assertStatus(200)->assertJsonPath('data.status', 'executed');
        $this->assertAmbiguityLifecycleInvariant($execute->json('data.ambiguities'));

        // The resolved ambiguity remains coherent on the terminal payload.
        $execute->assertJsonPath('data.ambiguities.0.blocking', false);
        $this->assertNotNull($execute->json('data.ambiguities.0.selected_candidate_id'));
    }

    // 5. Narrow-then-ordinal: blocking only clears on the resolving turn -------

    public function test_narrow_then_resolve_clears_blocking_only_when_resolved(): void
    {
        $this->actingAsUser();

        $id = $this->interpretAmbiguous();

        $narrow = $this->postJson("/api/actions/{$id}/refine", ['text' => 'the company']);
        $narrow->assertJsonPath('data.ambiguities.0.blocking', true);
        $this->assertAmbiguityLifecycleInvariant($narrow->json('data.ambiguities'));

        $resolve = $this->postJson("/api/actions/{$id}/refine", ['text' => 'the first one']);
        $resolve->assertStatus(200)
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.ambiguities.0.blocking', false);
        $this->assertNotNull($resolve->json('data.ambiguities.0.selected_candidate_id'));
        $this->assertAmbiguityLifecycleInvariant($resolve->json('data.ambiguities'));
    }
}
