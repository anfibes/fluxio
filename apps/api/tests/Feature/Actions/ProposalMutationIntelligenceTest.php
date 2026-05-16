<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Carbon\Carbon;
use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProposalMutationIntelligenceTest extends TestCase
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

    /**
     * A ready schedule_call proposal with lead, date, time — no missing, no ambiguities.
     */
    private function readyScheduleCallProposal(User $user, array $overrides = []): ActionProposal
    {
        return ActionProposal::create(array_merge([
            'user_id'    => $user->id,
            'intent'     => 'schedule_call',
            'status'     => 'ready',
            'confidence' => 0.85,
            'source_text' => 'Call Rossini',
            'entities'   => ['lead' => 'Rossini'],
            'missing'    => [],
            'warnings'   => [],
            'editable_fields' => [
                ['key' => 'lead',  'label' => 'Lead',  'value' => 'Rossini',                           'source' => 'detected', 'required' => true],
                ['key' => 'date',  'label' => 'Date',  'value' => now()->addDay()->toDateString(),      'source' => 'detected', 'required' => true],
                ['key' => 'time',  'label' => 'Time',  'value' => '09:00',                             'source' => 'detected', 'required' => true],
            ],
            'changes' => [
                ['type' => 'schedule', 'label' => 'Schedule call', 'module' => 'calendar', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [],
        ], $overrides));
    }

    // ── 1. Incremental refinement chain ──────────────────────────────────────

    public function test_incremental_chain_call_rossi_ambiguity_date_time(): void
    {
        $user = $this->actingAsUser();

        // Step 1: interpret "Call Rossi" — draft with lead ambiguity
        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $r1->json('data.id');
        $this->assertEquals('draft', $r1->json('data.status'));

        // Step 2: resolve ambiguity "Rossi SRL" — still draft (date+time still missing)
        $r2 = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Rossi SRL']);
        $r2->assertStatus(200)
            ->assertJsonPath('data.id', $proposalId)
            ->assertJsonPath('data.status', 'draft');

        $fields2 = collect($r2->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Rossi SRL', $fields2['lead']['value']);

        // Step 3: fill date + time "Tomorrow morning" — now ready
        $r3 = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Tomorrow morning']);
        $r3->assertStatus(200)
            ->assertJsonPath('data.id', $proposalId)
            ->assertJsonPath('data.status', 'ready');

        $fields3 = collect($r3->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals(now()->addDay()->toDateString(), $fields3['date']['value']);
        $this->assertEquals('09:00', $fields3['time']['value']);
        $this->assertEquals('Rossi SRL', $fields3['lead']['value']);

        // Step 4: update time only "At 10:30"
        $r4 = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'At 10:30']);
        $r4->assertStatus(200)
            ->assertJsonPath('data.id', $proposalId)
            ->assertJsonPath('data.status', 'ready');

        $fields4 = collect($r4->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('10:30', $fields4['time']['value']);
        $this->assertEquals(now()->addDay()->toDateString(), $fields4['date']['value']); // date preserved
        $this->assertEquals('Rossi SRL', $fields4['lead']['value']);                     // lead preserved

        // last_refinement must contain ONLY time change
        $changeFields4 = collect($r4->json('data.last_refinement.changes'))->pluck('field')->all();
        $this->assertContains('time', $changeFields4);
        $this->assertNotContains('date', $changeFields4);
        $this->assertNotContains('lead', $changeFields4);
    }

    public function test_incremental_chain_preserves_same_proposal_id_throughout(): void
    {
        $user = $this->actingAsUser();

        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $r1->json('data.id');

        $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Rossi SRL'])
            ->assertJsonPath('data.id', $proposalId);

        $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Tomorrow morning'])
            ->assertJsonPath('data.id', $proposalId);

        $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'At 10:30'])
            ->assertJsonPath('data.id', $proposalId);
    }

    // ── 2. Ambiguity resolution ───────────────────────────────────────────────

    public function test_ambiguity_resolution_updates_selected_candidate_id(): void
    {
        $user = $this->actingAsUser();

        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $r1->json('data.id');

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Rossi SRL']);

        $this->assertEquals(7, $response->json('data.ambiguities.0.selected_candidate_id'));
    }

    public function test_ambiguity_resolution_updates_lead_editable_field(): void
    {
        $user = $this->actingAsUser();

        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $r1->json('data.id');

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Rossi SRL']);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Rossi SRL', $fields['lead']['value']);
        $this->assertEquals('detected', $fields['lead']['source']);
    }

    public function test_resolved_ambiguity_alone_does_not_unblock_readiness(): void
    {
        $user = $this->actingAsUser();

        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $r1->json('data.id');

        // Ambiguity resolved but date+time still missing
        $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Rossi SRL'])
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_multiple_company_ambiguity_stays_unresolved(): void
    {
        $user = $this->actingAsUser();

        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $r1->json('data.id');

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'The company']);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.ambiguities.0.selected_candidate_id', null);

        $this->assertNotEmpty($response->json('data.warnings'));
    }

    // ── 3. Date replacement ──────────────────────────────────────────────────

    public function test_friday_instead_replaces_date_only(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user);

        $expectedFriday = Carbon::parse('next friday')->toDateString();

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Friday instead']);

        $response->assertStatus(200);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals($expectedFriday, $fields['date']['value']);
        $this->assertEquals('09:00', $fields['time']['value']);    // time preserved
        $this->assertEquals('Rossini', $fields['lead']['value']); // lead preserved
    }

    public function test_friday_instead_last_refinement_contains_only_date_change(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Friday instead']);

        $changeFields = collect($response->json('data.last_refinement.changes'))->pluck('field')->all();
        $this->assertContains('date', $changeFields);
        $this->assertNotContains('time', $changeFields);
        $this->assertNotContains('lead', $changeFields);
    }

    public function test_tomorrow_replaces_date_preserves_time(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user, [
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossini',                         'source' => 'detected', 'required' => true],
                ['key' => 'date', 'label' => 'Date', 'value' => now()->addDays(5)->toDateString(), 'source' => 'detected', 'required' => true],
                ['key' => 'time', 'label' => 'Time', 'value' => '14:00',                           'source' => 'detected', 'required' => true],
            ],
        ]);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Tomorrow']);

        $response->assertStatus(200);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals(now()->addDay()->toDateString(), $fields['date']['value']);
        $this->assertEquals('14:00', $fields['time']['value']); // time preserved

        $changeFields = collect($response->json('data.last_refinement.changes'))->pluck('field')->all();
        $this->assertContains('date', $changeFields);
        $this->assertNotContains('time', $changeFields);
    }

    public function test_date_replacement_summary_says_date_updated(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user, [
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossini',                         'source' => 'detected', 'required' => true],
                ['key' => 'date', 'label' => 'Date', 'value' => now()->addDays(5)->toDateString(), 'source' => 'detected', 'required' => true],
                ['key' => 'time', 'label' => 'Time', 'value' => '14:00',                           'source' => 'detected', 'required' => true],
            ],
        ]);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Tomorrow']);

        $response->assertJsonPath('data.last_refinement.summary', 'Date updated.');
    }

    // ── 4. Missing field completion ──────────────────────────────────────────

    public function test_tomorrow_at_nine_fills_date_and_time_and_becomes_ready(): void
    {
        $user = $this->actingAsUser();

        // Call Rossini creates a draft with lead resolved but date+time missing
        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossini']);
        $proposalId = $r1->json('data.id');
        $this->assertEquals('draft', $r1->json('data.status'));

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Tomorrow at 9']);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ready');

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals(now()->addDay()->toDateString(), $fields['date']['value']);
        $this->assertEquals('09:00', $fields['time']['value']);

        $this->assertEmpty($response->json('data.missing'));
    }

    public function test_tomorrow_at_nine_removes_date_and_time_from_missing(): void
    {
        $user = $this->actingAsUser();

        $r1         = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossini']);
        $proposalId = $r1->json('data.id');

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Tomorrow at 9']);

        $missingKeys = collect($response->json('data.missing'))->pluck('key')->all();
        $this->assertNotContains('date', $missingKeys);
        $this->assertNotContains('time', $missingKeys);
    }

    public function test_missing_completion_summary_says_date_and_time_added(): void
    {
        $user = $this->actingAsUser();

        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossini']);
        $proposalId = $r1->json('data.id');

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Tomorrow at 9']);

        $response->assertJsonPath('data.last_refinement.summary', 'Date and time added.');
    }

    // ── 5. Time-only mutation ────────────────────────────────────────────────

    public function test_at_time_updates_only_time(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'At 10:30']);

        $response->assertStatus(200);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('10:30', $fields['time']['value']);
        $this->assertEquals(now()->addDay()->toDateString(), $fields['date']['value']); // preserved
        $this->assertEquals('Rossini', $fields['lead']['value']);                       // preserved
    }

    public function test_at_time_last_refinement_contains_only_time_change(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'At 14:00']);

        $changeFields = collect($response->json('data.last_refinement.changes'))->pluck('field')->all();
        $this->assertContains('time', $changeFields);
        $this->assertNotContains('date', $changeFields);
        $this->assertNotContains('lead', $changeFields);
    }

    public function test_time_update_summary_says_time_updated(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'At 10:30']);

        $response->assertJsonPath('data.last_refinement.summary', 'Time updated.');
    }

    public function test_at_time_status_remains_ready(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user);

        $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'At 10:30'])
            ->assertJsonPath('data.status', 'ready');
    }

    public function test_at_time_pm_is_parsed_correctly(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'At 3pm']);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('15:00', $fields['time']['value']);
    }

    // ── 6. Non-destructive: unrelated fields remain untouched ───────────────

    public function test_time_mutation_preserves_resolved_ambiguity(): void
    {
        $user = $this->actingAsUser();

        // Interpret "Call Rossi" → resolve → fill date → update time
        $r1         = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $r1->json('data.id');

        $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Rossi SRL']);
        $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Tomorrow morning']);

        $response = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'At 14:00']);

        $response->assertStatus(200);

        // Lead still Rossi SRL
        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Rossi SRL', $fields['lead']['value']);

        // Ambiguity still marked as resolved
        $this->assertEquals(7, $response->json('data.ambiguities.0.selected_candidate_id'));

        // Time updated, date and lead NOT in changes
        $changeFields = collect($response->json('data.last_refinement.changes'))->pluck('field')->all();
        $this->assertContains('time', $changeFields);
        $this->assertNotContains('lead', $changeFields);
        $this->assertNotContains('date', $changeFields);
    }

    public function test_date_mutation_does_not_affect_entities(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user);

        $originalEntities = $proposal->entities;

        $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Tomorrow']);

        $proposal->refresh();
        $this->assertEquals($originalEntities, $proposal->entities);
    }

    // ── 7. Priority mutation ─────────────────────────────────────────────────

    public function test_high_priority_adds_priority_editable_field(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'High priority']);

        $response->assertStatus(200);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertArrayHasKey('priority', $fields->all());
        $this->assertEquals('high', $fields['priority']['value']);
    }

    public function test_priority_mutation_preserves_all_other_fields(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'High priority']);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Rossini', $fields['lead']['value']);
        $this->assertEquals(now()->addDay()->toDateString(), $fields['date']['value']);
        $this->assertEquals('09:00', $fields['time']['value']);
    }

    public function test_priority_mutation_summary_says_priority_set(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'High priority']);

        $response->assertJsonPath('data.last_refinement.summary', 'Priority set.');
    }

    public function test_urgent_also_sets_high_priority(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Urgent']);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('high', $fields['priority']['value']);
    }

    // ── 8. Date-only mutations (tomorrow standalone, next weekday) ───────────

    public function test_tomorrow_standalone_fills_date_without_touching_time(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user, [
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossini',                         'source' => 'detected', 'required' => true],
                ['key' => 'date', 'label' => 'Date', 'value' => now()->addDays(5)->toDateString(), 'source' => 'detected', 'required' => true],
                ['key' => 'time', 'label' => 'Time', 'value' => '14:00',                           'source' => 'detected', 'required' => true],
            ],
        ]);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Tomorrow']);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals(now()->addDay()->toDateString(), $fields['date']['value']);
        $this->assertEquals('14:00', $fields['time']['value']); // unchanged
    }

    public function test_next_friday_fills_date_correctly(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Next Friday']);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals(Carbon::parse('next friday')->toDateString(), $fields['date']['value']);
    }

    // ── 9. Lifecycle protection ──────────────────────────────────────────────

    public function test_refine_rejected_for_confirmed_proposal(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user, ['status' => 'confirmed']);

        $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'At 10:00'])
            ->assertStatus(422);

        $proposal->refresh();
        $this->assertEquals('confirmed', $proposal->status);
    }

    public function test_refine_rejected_for_executed_proposal(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user, ['status' => 'executed']);

        $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'At 10:00'])
            ->assertStatus(422);

        $proposal->refresh();
        $this->assertEquals('executed', $proposal->status);
    }

    public function test_refine_rejected_for_failed_proposal(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user, ['status' => 'failed']);

        $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'At 10:00'])
            ->assertStatus(422);

        $proposal->refresh();
        $this->assertEquals('failed', $proposal->status);
    }

    // ── 10. Dot time format ──────────────────────────────────────────────────

    public function test_dot_time_format_parsed_as_colon_time(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'At 10.30']);

        $response->assertStatus(200);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('10:30', $fields['time']['value']);
        $this->assertEquals(now()->addDay()->toDateString(), $fields['date']['value']); // date preserved
        $this->assertEquals('Rossini', $fields['lead']['value']);                       // lead preserved

        $changeFields = collect($response->json('data.last_refinement.changes'))->pluck('field')->all();
        $this->assertContains('time', $changeFields);
        $this->assertNotContains('date', $changeFields);
    }

    // ── 11. Duplicate-field protection ───────────────────────────────────────

    public function test_second_time_refinement_updates_single_time_field(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user);

        // First refinement: 09:00 → 10:30
        $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'At 10:30'])
            ->assertStatus(200);

        // Second refinement: 10:30 → 11:00
        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'At 11:00']);

        $response->assertStatus(200);

        $fields         = $response->json('data.editable_fields');
        $timeFields     = array_values(array_filter($fields, fn (array $f) => $f['key'] === 'time'));

        $this->assertCount(1, $timeFields, 'editable_fields must contain exactly one time entry');
        $this->assertEquals('11:00', $timeFields[0]['value']);

        $changes    = $response->json('data.last_refinement.changes');
        $timeChange = collect($changes)->firstWhere('field', 'time');
        $this->assertNotNull($timeChange);
        $this->assertEquals('10:30', $timeChange['from']);
        $this->assertEquals('11:00', $timeChange['to']);
    }

    // ── 12. Clear operation ───────────────────────────────────────────────────

    private function readyScheduleCallProposalWithHighPriority(User $user): ActionProposal
    {
        return $this->readyScheduleCallProposal($user, [
            'editable_fields' => [
                ['key' => 'lead',     'label' => 'Lead',     'value' => 'Rossini',                      'source' => 'detected', 'required' => true],
                ['key' => 'date',     'label' => 'Date',     'value' => now()->addDay()->toDateString(), 'source' => 'detected', 'required' => true],
                ['key' => 'time',     'label' => 'Time',     'value' => '09:00',                         'source' => 'detected', 'required' => true],
                ['key' => 'priority', 'label' => 'Priority', 'value' => 'high',                          'source' => 'detected', 'required' => false],
            ],
        ]);
    }

    public function test_remove_priority_clears_priority_from_editable_fields(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithHighPriority($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Remove priority']);

        $response->assertStatus(200);

        $fieldKeys = collect($response->json('data.editable_fields'))->pluck('key')->all();
        $this->assertNotContains('priority', $fieldKeys);
    }

    public function test_clear_priority_preserves_all_other_fields(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithHighPriority($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Remove priority']);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Rossini', $fields['lead']['value']);
        $this->assertEquals(now()->addDay()->toDateString(), $fields['date']['value']);
        $this->assertEquals('09:00', $fields['time']['value']);
    }

    public function test_clear_priority_summary_says_priority_cleared(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithHighPriority($user);

        $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Remove priority'])
            ->assertJsonPath('data.last_refinement.summary', 'Priority cleared.');
    }

    public function test_clear_priority_last_refinement_change_has_null_to(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithHighPriority($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Remove priority']);

        $change = collect($response->json('data.last_refinement.changes'))->firstWhere('field', 'priority');
        $this->assertNotNull($change);
        $this->assertEquals('high', $change['from']);
        $this->assertNull($change['to']);
    }

    public function test_clear_priority_status_remains_ready(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithHighPriority($user);

        $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Remove priority'])
            ->assertJsonPath('data.status', 'ready');
    }

    public function test_clear_on_absent_priority_produces_no_change_record(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user); // no priority field

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Remove priority']);

        // No previous value → no change record, summary is "No changes applied."
        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.last_refinement.changes'));
        $response->assertJsonPath('data.last_refinement.summary', 'No changes applied.');
    }

    public function test_clear_priority_is_persisted_to_database(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithHighPriority($user);

        $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Clear priority'])
            ->assertStatus(200);

        $proposal->refresh();

        $fieldKeys = collect($proposal->editable_fields)->pluck('key')->all();
        $this->assertNotContains('priority', $fieldKeys);
        $this->assertEquals('Priority cleared.', $proposal->last_refinement['summary']);
    }

    // ── 13. Persistence assertions ───────────────────────────────────────────

    // ── 14. Contextual mutation semantics ────────────────────────────────────

    private function readyScheduleCallProposalWithParticipants(User $user, array $participants = ['Luca', 'Marco']): ActionProposal
    {
        return $this->readyScheduleCallProposal($user, [
            'editable_fields' => [
                ['key' => 'lead',         'label' => 'Lead',         'value' => 'Rossini',                      'source' => 'detected', 'required' => true],
                ['key' => 'date',         'label' => 'Date',         'value' => now()->addDay()->toDateString(), 'source' => 'detected', 'required' => true],
                ['key' => 'time',         'label' => 'Time',         'value' => '09:00',                        'source' => 'detected', 'required' => true],
                ['key' => 'participants', 'label' => 'Participants', 'value' => $participants,                  'source' => 'detected', 'required' => false],
            ],
        ]);
    }

    public function test_move_it_to_friday_changes_date_preserves_other_fields(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Move it to Friday']);

        $response->assertStatus(200);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals(\Carbon\Carbon::parse('next friday')->toDateString(), $fields['date']['value']);
        $this->assertEquals('09:00', $fields['time']['value']);   // time preserved
        $this->assertEquals('Rossini', $fields['lead']['value']); // lead preserved
    }

    public function test_move_it_to_friday_last_refinement_contains_only_date_change(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Move it to Friday']);

        $changeFields = collect($response->json('data.last_refinement.changes'))->pluck('field')->all();
        $this->assertContains('date', $changeFields);
        $this->assertNotContains('time', $changeFields);
        $this->assertNotContains('lead', $changeFields);
    }

    public function test_add_participant_appends_to_participants_list(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithParticipants($user, ['Luca']);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Add Mario too']);

        $response->assertStatus(200);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals(['Luca', 'Mario'], $fields['participants']['value']);
    }

    public function test_add_participant_summary_says_participant_added(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithParticipants($user, ['Luca']);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Add Mario too']);

        $response->assertJsonPath('data.last_refinement.summary', 'Participant added.');
    }

    public function test_add_participant_change_record_shows_from_and_to_arrays(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithParticipants($user, ['Luca']);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Add Mario too']);

        $change = collect($response->json('data.last_refinement.changes'))->firstWhere('field', 'participants');
        $this->assertNotNull($change);
        $this->assertEquals(['Luca'], $change['from']);
        $this->assertEquals(['Luca', 'Mario'], $change['to']);
        $this->assertEquals('append', $change['operation']);
    }

    public function test_add_existing_participant_is_idempotent(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithParticipants($user, ['Luca']);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Add Luca too']);

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.last_refinement.changes'));
        $response->assertJsonPath('data.last_refinement.summary', 'No changes applied.');

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals(['Luca'], $fields['participants']['value']); // unchanged
    }

    public function test_remove_participant_removes_from_list(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithParticipants($user, ['Luca', 'Marco']);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Remove Marco']);

        $response->assertStatus(200);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals(['Luca'], $fields['participants']['value']);
    }

    public function test_remove_participant_summary_says_participant_removed(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithParticipants($user, ['Luca', 'Marco']);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Remove Marco']);

        $response->assertJsonPath('data.last_refinement.summary', 'Participant removed.');
    }

    public function test_remove_participant_change_record_shows_from_and_to_arrays(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithParticipants($user, ['Luca', 'Marco']);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Remove Marco']);

        $change = collect($response->json('data.last_refinement.changes'))->firstWhere('field', 'participants');
        $this->assertNotNull($change);
        $this->assertEquals(['Luca', 'Marco'], $change['from']);
        $this->assertEquals(['Luca'], $change['to']);
        $this->assertEquals('remove', $change['operation']);
    }

    public function test_remove_nonexistent_participant_produces_no_change(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithParticipants($user, ['Luca', 'Marco']);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Remove Giovanni']);

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.last_refinement.changes'));
        $response->assertJsonPath('data.last_refinement.summary', 'No changes applied.');
    }

    public function test_replace_participant_replaces_specific_item(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithParticipants($user, ['Luca', 'Marco']);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Replace Luca with Giovanni']);

        $response->assertStatus(200);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals(['Giovanni', 'Marco'], $fields['participants']['value']);
    }

    public function test_replace_participant_summary_says_participants_updated(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithParticipants($user, ['Luca', 'Marco']);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Replace Luca with Giovanni']);

        $response->assertJsonPath('data.last_refinement.summary', 'Participants updated.');
    }

    public function test_replace_participant_change_record_shows_target(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithParticipants($user, ['Luca', 'Marco']);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Replace Luca with Giovanni']);

        $change = collect($response->json('data.last_refinement.changes'))->firstWhere('field', 'participants');
        $this->assertNotNull($change);
        $this->assertEquals('Luca', $change['target']);
        $this->assertEquals(['Luca', 'Marco'], $change['from']);
        $this->assertEquals(['Giovanni', 'Marco'], $change['to']);
    }

    public function test_collection_mutation_preserves_unrelated_scalar_fields(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithParticipants($user, ['Luca']);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Add Mario too']);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Rossini', $fields['lead']['value']);
        $this->assertEquals(now()->addDay()->toDateString(), $fields['date']['value']);
        $this->assertEquals('09:00', $fields['time']['value']);
    }

    public function test_collection_mutation_status_remains_ready(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithParticipants($user, ['Luca']);

        $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Add Mario too'])
            ->assertJsonPath('data.status', 'ready');
    }

    public function test_add_participant_is_persisted_to_database(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposalWithParticipants($user, ['Luca']);

        $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Add Mario too'])
            ->assertStatus(200);

        $proposal->refresh();

        $fields = collect($proposal->editable_fields)->keyBy('key');
        $this->assertEquals(['Luca', 'Mario'], $fields['participants']['value']);
        $this->assertEquals('Participant added.', $proposal->last_refinement['summary']);
    }



    public function test_time_mutation_is_fully_persisted_to_database(): void
    {
        $user     = $this->actingAsUser();
        $proposal = $this->readyScheduleCallProposal($user);

        $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'At 14:00'])
            ->assertStatus(200);

        $proposal->refresh();

        $this->assertEquals('ready', $proposal->status);

        $fields = collect($proposal->editable_fields)->keyBy('key');
        $this->assertEquals('14:00', $fields['time']['value']);

        $this->assertEquals('At 14:00', $proposal->last_refinement['text']);
        $this->assertEquals('Time updated.', $proposal->last_refinement['summary']);
        $this->assertNotEmpty($proposal->last_refinement['changes']);

        $this->assertEquals([], $proposal->ambiguities);
    }
}
