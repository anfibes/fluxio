<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Carbon\Carbon;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Leads\Models\Lead;
use Fluxio\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsDemoLeads;
use Tests\TestCase;

/**
 * Regression: the confirmed proposal state must be what execution runs.
 *
 * CreateTaskActionExecutor reads changes[0].payload, but syncChangePayloads only
 * updates keys the payload already declared — so a scalar field introduced AFTER
 * interpretation (priority via refinement, lead via ambiguity resolution) was shown
 * as authoritative in editable_fields/entities yet never reached the executed
 * payload: the task was created with the default priority / no lead link. These
 * tests pin the contract that the create_task operational payload mirrors the
 * authoritative entities for its declared scalar fields (lead, date, time,
 * priority), while unrelated intents' payloads gain nothing.
 */
class CreateTaskRefinementExecutionConsistencyTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoLeads;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-05-30 08:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function payload(array $data): array
    {
        return $data['changes'][0]['payload'] ?? [];
    }

    private function fieldValue(array $data, string $key): mixed
    {
        foreach ($data['editable_fields'] as $field) {
            if ($field['key'] === $key) {
                return $field['value'];
            }
        }

        return null;
    }

    // ── Priority introduced via refinement ──────────────────────────────────

    public function test_priority_added_via_refinement_reaches_payload_and_execution(): void
    {
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Create a task for Rossini'])
            ->assertStatus(200)
            ->assertJsonPath('data.intent', 'create_task')
            ->assertJsonPath('data.status', 'ready');

        $proposalId = $interpret->json('data.id');

        // The initial operational payload declares no priority.
        $this->assertArrayNotHasKey('priority', $this->payload($interpret->json('data')));

        $data = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'make it low priority'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'ready')
            ->json('data');

        // Authoritative state and operational payload agree.
        $this->assertSame('low', $this->fieldValue($data, 'priority'));
        $this->assertSame('low', $data['entities']['priority'] ?? null);
        $this->assertSame('low', $this->payload($data)['priority'] ?? null);

        // No unrelated keys were invented alongside the injected one.
        $this->assertEqualsCanonicalizing(
            ['title', 'lead', 'priority'],
            array_keys($this->payload($data)),
        );

        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);
        $this->postJson("/api/actions/{$proposalId}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $task = Task::sole();
        $this->assertSame('low', $task->priority);
    }

    public function test_priority_cleared_via_refinement_is_removed_from_payload_and_execution(): void
    {
        $this->actingAsUser();

        $proposalId = $this->postJson('/api/actions/interpret', ['text' => 'Create a task for Rossini'])
            ->assertStatus(200)
            ->json('data.id');

        $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'High priority'])
            ->assertStatus(200)
            ->assertJsonPath('data.changes.0.payload.priority', 'high');

        $data = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Remove priority'])
            ->assertStatus(200)
            ->json('data');

        // The cleared field leaves every authoritative surface, payload included.
        $this->assertNull($this->fieldValue($data, 'priority'));
        $this->assertArrayNotHasKey('priority', $data['entities']);
        $this->assertArrayNotHasKey('priority', $this->payload($data));

        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);
        $this->postJson("/api/actions/{$proposalId}/execute")->assertStatus(200);

        // Execution falls back to the domain default — matching the confirmed
        // state, which showed no priority.
        $this->assertSame('normal', Task::sole()->priority);
    }

    // ── Lead promoted by ambiguity resolution ───────────────────────────────

    public function test_lead_resolved_from_ambiguity_reaches_payload_and_links_task(): void
    {
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Create a task for Rossi'])
            ->assertStatus(200)
            ->assertJsonPath('data.intent', 'create_task')
            ->assertJsonPath('data.status', 'draft');

        $proposalId = $interpret->json('data.id');

        // Ambiguous lead: not yet part of the operational payload.
        $this->assertArrayNotHasKey('lead', $this->payload($interpret->json('data')));

        // "the person" uniquely matches Mario Rossi → resolved.
        $data = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'the person'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'ready')
            ->json('data');

        $this->assertSame('Mario Rossi', $data['entities']['lead'] ?? null);
        $this->assertSame('Mario Rossi', $this->payload($data)['lead'] ?? null);

        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);
        $this->postJson("/api/actions/{$proposalId}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        // The executed task is linked to the exact lead the user selected:
        // look up Mario Rossi by name and compare his real database id.
        $expectedLead = Lead::where('name', 'Mario Rossi')->sole();
        $this->assertSame($expectedLead->id, Task::sole()->lead_id);
    }

    // ── Temporal coherence is preserved ─────────────────────────────────────

    public function test_temporal_fields_added_via_refinement_stay_coherent_with_due_at(): void
    {
        $this->actingAsUser();

        $proposalId = $this->postJson('/api/actions/interpret', ['text' => 'Create a task for Rossini'])
            ->assertStatus(200)
            ->json('data.id');

        $data = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'tomorrow at 10:00'])
            ->assertStatus(200)
            ->json('data');

        // date/time land in the payload alongside the composed due_at, mirroring
        // the shape interpretation itself would have produced.
        $payload = $this->payload($data);
        $this->assertSame('2026-05-31', $payload['date'] ?? null);
        $this->assertSame('10:00', $payload['time'] ?? null);
        $this->assertSame('2026-05-31 10:00', $payload['due_at'] ?? null);

        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);
        $this->postJson("/api/actions/{$proposalId}/execute")->assertStatus(200);

        $this->assertSame('2026-05-31 10:00', Task::sole()->due_at?->format('Y-m-d H:i'));
    }

    // ── Negative: unrelated intents and payloads are untouched ──────────────

    public function test_refined_priority_is_not_injected_into_schedule_call_payload(): void
    {
        $user = $this->actingAsUser();

        $proposal = ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'schedule_call',
            'status' => 'ready',
            'confidence' => 0.9,
            'source_text' => 'Schedule a call with Rossini tomorrow at 10:00',
            'entities' => ['lead' => 'Rossini', 'date' => '2026-05-31', 'time' => '10:00'],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossini', 'source' => 'detected', 'required' => true],
                ['key' => 'date', 'label' => 'Date', 'value' => '2026-05-31', 'source' => 'detected', 'required' => true],
                ['key' => 'time', 'label' => 'Time', 'value' => '10:00', 'source' => 'detected', 'required' => true],
            ],
            'changes' => [
                ['type' => 'schedule', 'label' => 'Schedule call', 'module' => 'calendar', 'payload' => []],
            ],
            'needs_confirmation' => true,
        ]);

        $data = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'High priority'])
            ->assertStatus(200)
            ->json('data');

        // The field is applied to the authoritative state …
        $this->assertSame('high', $this->fieldValue($data, 'priority'));
        // … but the schedule payload (empty by convention) gains nothing.
        $this->assertSame([], $data['changes'][0]['payload']);
    }
}
