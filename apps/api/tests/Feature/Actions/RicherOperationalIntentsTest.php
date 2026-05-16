<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Carbon\Carbon;
use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RicherOperationalIntentsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-05-10 08:00:00');
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

    // ── prepare_contract_from_quote ─────────────────────────────────────────

    public function test_prepare_contract_command_produces_correct_intent(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Prepare a contract for Rossini',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'prepare_contract_from_quote');
    }

    public function test_prepare_contract_with_lead_is_ready(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Prepare a contract for Rossini',
        ]);

        $response->assertJsonPath('data.status', 'ready');
    }

    public function test_prepare_contract_without_lead_is_draft(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Prepare a contract for the client',
        ]);

        $response->assertJsonPath('data.status', 'draft');

        $missingKeys = collect($response->json('data.missing'))->pluck('key')->all();
        $this->assertContains('lead', $missingKeys);
    }

    public function test_prepare_contract_editable_fields_contain_lead(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Prepare a contract for Rossini',
        ]);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertArrayHasKey('lead', $fields->all());
        $this->assertEquals('Rossini', $fields['lead']['value']);
    }

    public function test_prepare_contract_has_changes_with_correct_module(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Prepare a contract for Rossini',
        ]);

        $changes = $response->json('data.changes');
        $this->assertNotEmpty($changes);
        $this->assertEquals('tasks', $changes[0]['module']);
        $this->assertEquals('create', $changes[0]['type']);
    }

    // ── assign_lead ──────────────────────────────────────────────────────────

    public function test_assign_lead_command_produces_correct_intent(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Assign Rossini to Marco',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'assign_lead');
    }

    public function test_assign_lead_with_both_entities_is_ready(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Assign Rossini to Marco',
        ]);

        $response->assertJsonPath('data.status', 'ready');
    }

    public function test_assign_lead_editable_fields_contain_lead_and_assignee(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Assign Rossini to Marco',
        ]);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertArrayHasKey('lead', $fields->all());
        $this->assertArrayHasKey('assignee', $fields->all());
        $this->assertEquals('Rossini', $fields['lead']['value']);
        $this->assertEquals('Marco', $fields['assignee']['value']);
    }

    public function test_assign_lead_without_assignee_is_draft(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Assign Rossini',
        ]);

        $response->assertJsonPath('data.status', 'draft');

        $missingKeys = collect($response->json('data.missing'))->pluck('key')->all();
        $this->assertContains('assignee', $missingKeys);
    }

    public function test_assign_lead_has_changes_with_correct_module(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Assign Rossini to Marco',
        ]);

        $changes = $response->json('data.changes');
        $this->assertNotEmpty($changes);
        $this->assertEquals('leads', $changes[0]['module']);
        $this->assertEquals('assign', $changes[0]['type']);
    }

    public function test_assign_lead_confidence_is_higher_than_unknown(): void
    {
        $this->actingAsUser();

        $assignResponse   = $this->postJson('/api/actions/interpret', ['text' => 'Assign Rossini to Marco']);
        $unknownResponse  = $this->postJson('/api/actions/interpret', ['text' => 'Show me the dashboard']);

        $this->assertGreaterThan(
            $unknownResponse->json('data.confidence'),
            $assignResponse->json('data.confidence')
        );
    }

    // ── schedule_meeting ─────────────────────────────────────────────────────

    public function test_schedule_meeting_command_produces_correct_intent(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Schedule a meeting with Rossini',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'schedule_meeting');
    }

    public function test_schedule_meeting_without_date_and_time_is_draft(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Schedule a meeting with Rossini',
        ]);

        $response->assertJsonPath('data.status', 'draft');

        $missingKeys = collect($response->json('data.missing'))->pluck('key')->all();
        $this->assertContains('date', $missingKeys);
        $this->assertContains('time', $missingKeys);
    }

    public function test_schedule_meeting_has_changes_with_correct_module(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Schedule a meeting with Rossini',
        ]);

        $changes = $response->json('data.changes');
        $this->assertNotEmpty($changes);
        $this->assertEquals('calendar', $changes[0]['module']);
        $this->assertEquals('schedule', $changes[0]['type']);
    }

    public function test_schedule_meeting_lead_field_is_detected(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Schedule a meeting with Rossini',
        ]);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertArrayHasKey('lead', $fields->all());
        $this->assertEquals('Rossini', $fields['lead']['value']);
    }

    // ── Proposal lifecycle for richer intents ────────────────────────────────

    public function test_prepare_contract_proposal_can_be_refined(): void
    {
        $this->actingAsUser();

        // Start with missing lead
        $r1 = $this->postJson('/api/actions/interpret', [
            'text' => 'Prepare a contract for the client',
        ]);
        $proposalId = $r1->json('data.id');
        $this->assertEquals('draft', $r1->json('data.status'));

        // Refine — note: the generic builder doesn't support Rossini entity resolution via refinement
        // The standard text-based field fill applies
        $r2 = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Tomorrow']);
        $r2->assertStatus(200);
        $this->assertEquals($proposalId, $r2->json('data.id')); // same proposal
    }

    public function test_assign_lead_proposal_preserves_id_across_refinement(): void
    {
        $this->actingAsUser();

        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Assign Rossini']);
        $proposalId = $r1->json('data.id');

        $r2 = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Tomorrow']);
        $this->assertEquals($proposalId, $r2->json('data.id'));
    }

    // ── Execution for richer intents ─────────────────────────────────────────

    public function test_prepare_contract_proposal_can_execute(): void
    {
        $this->actingAsUser();

        // Create ready proposal directly
        $user     = User::factory()->create();
        Sanctum::actingAs($user);

        $proposal = ActionProposal::create([
            'user_id'     => $user->id,
            'intent'      => 'prepare_contract_from_quote',
            'status'      => 'confirmed',
            'confidence'  => 0.75,
            'source_text' => 'Prepare a contract for Rossini',
            'entities'    => ['lead' => 'Rossini'],
            'missing'     => [],
            'warnings'    => [],
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossini', 'source' => 'detected', 'required' => true],
            ],
            'changes' => [
                ['type' => 'create', 'label' => 'Prepare Contract', 'module' => 'tasks', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [],
        ]);

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertEquals('contract_prepared', $response->json('data.execution_result.type'));
    }

    public function test_assign_lead_proposal_can_execute(): void
    {
        $this->actingAsUser();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $proposal = ActionProposal::create([
            'user_id'     => $user->id,
            'intent'      => 'assign_lead',
            'status'      => 'confirmed',
            'confidence'  => 0.8,
            'source_text' => 'Assign Rossini to Marco',
            'entities'    => ['lead' => 'Rossini', 'assignee' => 'Marco'],
            'missing'     => [],
            'warnings'    => [],
            'editable_fields' => [
                ['key' => 'lead',     'label' => 'Lead',     'value' => 'Rossini', 'source' => 'detected', 'required' => true],
                ['key' => 'assignee', 'label' => 'Assignee', 'value' => 'Marco',   'source' => 'detected', 'required' => true],
            ],
            'changes' => [
                ['type' => 'assign', 'label' => 'Assign Lead', 'module' => 'leads', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [],
        ]);

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertEquals('lead_assigned', $response->json('data.execution_result.type'));
        $this->assertEquals('Rossini', $response->json('data.execution_result.lead'));
        $this->assertEquals('Marco', $response->json('data.execution_result.assignee'));
    }

    public function test_schedule_meeting_proposal_can_execute(): void
    {
        $this->actingAsUser();

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $proposal = ActionProposal::create([
            'user_id'     => $user->id,
            'intent'      => 'schedule_meeting',
            'status'      => 'confirmed',
            'confidence'  => 0.7,
            'source_text' => 'Schedule a meeting with Rossini',
            'entities'    => ['lead' => 'Rossini'],
            'missing'     => [],
            'warnings'    => [],
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossini',                      'source' => 'detected', 'required' => true],
                ['key' => 'date', 'label' => 'Date', 'value' => now()->addDay()->toDateString(), 'source' => 'detected', 'required' => true],
                ['key' => 'time', 'label' => 'Time', 'value' => '10:00',                        'source' => 'detected', 'required' => true],
            ],
            'changes' => [
                ['type' => 'schedule', 'label' => 'Schedule Meeting', 'module' => 'calendar', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [],
        ]);

        $response = $this->postJson("/api/actions/{$proposal->id}/execute");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertEquals('meeting_scheduled', $response->json('data.execution_result.type'));
    }

    // ── Full-command richer intent regression tests ───────────────────────────

    public function test_schedule_meeting_full_command_with_lead_date_time_is_ready(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Schedule a meeting with Rossini tomorrow at 4pm',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'schedule_meeting')
            ->assertJsonPath('data.status', 'ready');

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Rossini', $fields['lead']['value']);
        $this->assertEquals(now()->addDay()->toDateString(), $fields['date']['value']);
        $this->assertEquals('16:00', $fields['time']['value']);
    }

    public function test_schedule_meeting_full_command_no_missing_fields_when_ready(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Schedule a meeting with Rossini tomorrow at 4pm',
        ]);

        $this->assertEmpty($response->json('data.missing'));
        $this->assertEmpty($response->json('data.ambiguities'));
    }

    public function test_schedule_meeting_ambiguous_lead_produces_blocking_ambiguity(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Schedule a meeting with Rossi tomorrow morning',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'schedule_meeting')
            ->assertJsonPath('data.status', 'draft');

        // Lead must appear as a blocking ambiguity, NOT as a plain missing field
        $missingKeys = collect($response->json('data.missing'))->pluck('key')->all();
        $this->assertNotContains('lead', $missingKeys);

        $ambiguities = $response->json('data.ambiguities');
        $this->assertNotEmpty($ambiguities);
        $this->assertEquals('lead', $ambiguities[0]['key']);
        $this->assertTrue($ambiguities[0]['blocking']);
        $this->assertNull($ambiguities[0]['selected_candidate_id']);
        $this->assertNotEmpty($ambiguities[0]['candidates']);
    }

    public function test_schedule_meeting_ambiguous_lead_date_and_time_are_detected(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Schedule a meeting with Rossi tomorrow morning',
        ]);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals(now()->addDay()->toDateString(), $fields['date']['value']);
        $this->assertEquals('09:00', $fields['time']['value']);
    }

    public function test_schedule_meeting_ambiguous_lead_resolves_to_ready(): void
    {
        $this->actingAsUser();

        $r1         = $this->postJson('/api/actions/interpret', ['text' => 'Schedule a meeting with Rossi tomorrow morning']);
        $proposalId = $r1->json('data.id');

        // Resolve the ambiguity — same refinement mechanism as schedule_call
        $r2 = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Rossi SRL']);
        $r2->assertStatus(200)
            ->assertJsonPath('data.id', $proposalId)
            ->assertJsonPath('data.status', 'ready');

        // Lead resolved, date and time preserved
        $fields = collect($r2->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Rossi SRL', $fields['lead']['value']);
        $this->assertEquals(now()->addDay()->toDateString(), $fields['date']['value']);
        $this->assertEquals('09:00', $fields['time']['value']);
    }
}
