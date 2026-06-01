<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Carbon\Carbon;
use Fluxio\Actions\DTO\NormalizedMutation;
use Fluxio\Actions\Enums\RefinementCapabilityType;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Actions\Registry\IntentCapabilityRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Intent Capability Model tests.
 *
 * Covers:
 * 1. Registry returns capabilities for all known intents
 * 2. schedule_meeting allows participant append / remove / replace
 * 3. create_task rejects participant append
 * 4. Unsupported mutation does not mutate proposal
 * 5. Unsupported mutation adds a warning
 * 6. Existing valid refinement behavior still works (regression)
 * 7. Ambiguity resolution still works for supported intents
 * 8. Ambiguity resolution is not attempted for unsupported intents
 */
class IntentCapabilityTest extends TestCase
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

    // ── 1. Registry coverage ─────────────────────────────────────────────────

    public function test_capability_registry_has_all_known_intents(): void
    {
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $this->assertNotNull($registry->find('create_task'));
        $this->assertNotNull($registry->find('schedule_call'));
        $this->assertNotNull($registry->find('schedule_meeting'));
        $this->assertNotNull($registry->find('assign_lead'));
        $this->assertNotNull($registry->find('prepare_contract_from_quote'));
    }

    public function test_unknown_intent_returns_null(): void
    {
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $this->assertNull($registry->find('does_not_exist'));
    }

    // ── 2. schedule_meeting participant mutations are allowed ─────────────────

    public function test_schedule_meeting_allows_participant_append(): void
    {
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $mutation = new NormalizedMutation(
            field: 'participants',
            label: 'Participants',
            value: 'Mario',
            operation: 'append',
        );

        $this->assertTrue($registry->allowsMutation('schedule_meeting', $mutation));
    }

    public function test_schedule_meeting_allows_participant_remove(): void
    {
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $mutation = new NormalizedMutation(
            field: 'participants',
            label: 'Participants',
            value: null,
            operation: 'remove',
            target: 'Mario',
        );

        $this->assertTrue($registry->allowsMutation('schedule_meeting', $mutation));
    }

    public function test_schedule_meeting_allows_participant_collection_replace(): void
    {
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $mutation = new NormalizedMutation(
            field: 'participants',
            label: 'Participants',
            value: 'Marco',
            operation: 'replace',
            target: 'Luca',
        );

        $this->assertTrue($registry->allowsMutation('schedule_meeting', $mutation));
    }

    public function test_schedule_meeting_supports_ambiguity_resolution(): void
    {
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $capability = $registry->find('schedule_meeting');
        $this->assertNotNull($capability);
        $this->assertTrue($capability->supportsAmbiguityResolution);
    }

    public function test_schedule_meeting_supports_collection_mutations(): void
    {
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $capability = $registry->find('schedule_meeting');
        $this->assertNotNull($capability);
        $this->assertTrue($capability->supportsCollectionMutations);
    }

    public function test_schedule_meeting_supports_contextual_references(): void
    {
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $capability = $registry->find('schedule_meeting');
        $this->assertNotNull($capability);
        $this->assertTrue($capability->supportsContextualReferences);
    }

    // ── 3. create_task rejects participant mutations ──────────────────────────

    public function test_create_task_rejects_participant_append(): void
    {
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $mutation = new NormalizedMutation(
            field: 'participants',
            label: 'Participants',
            value: 'Mario',
            operation: 'append',
        );

        $this->assertFalse($registry->allowsMutation('create_task', $mutation));
    }

    public function test_create_task_rejects_participant_remove(): void
    {
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $mutation = new NormalizedMutation(
            field: 'participants',
            label: 'Participants',
            value: null,
            operation: 'remove',
            target: 'Mario',
        );

        $this->assertFalse($registry->allowsMutation('create_task', $mutation));
    }

    public function test_create_task_does_not_support_collection_mutations(): void
    {
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $capability = $registry->find('create_task');
        $this->assertNotNull($capability);
        $this->assertFalse($capability->supportsCollectionMutations);
    }

    public function test_create_task_supports_ambiguity_resolution(): void
    {
        // create_task carries an optional lead that goes through entity resolution and
        // can become a blocking ambiguity. Any intent that can generate a blocking
        // ambiguity must be able to resolve it via refinement, or the proposal becomes
        // permanently unconfirmable.
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $capability = $registry->find('create_task');
        $this->assertNotNull($capability);
        $this->assertTrue($capability->supportsAmbiguityResolution);
        $this->assertContains(
            RefinementCapabilityType::ResolveAmbiguity,
            $capability->refinements,
        );
    }

    // ── 4 & 5. Unsupported mutation: no change + warning ─────────────────────

    private function createTaskProposal(User $user): ActionProposal
    {
        return ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'create_task',
            'status' => 'ready',
            'confidence' => 0.9,
            'source_text' => 'Create a task for Rossini',
            'entities' => ['lead' => 'Rossini'],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossini', 'source' => 'detected', 'required' => false],
            ],
            'changes' => [
                ['type' => 'create', 'label' => 'Create Task', 'module' => 'tasks', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [],
        ]);
    }

    public function test_participant_append_on_create_task_does_not_change_proposal(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->createTaskProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Add Mario too']);

        $response->assertStatus(200);

        // No participants field should have been added
        $fieldKeys = collect($response->json('data.editable_fields'))->pluck('key')->all();
        $this->assertNotContains('participants', $fieldKeys);

        // No changes applied
        $this->assertEmpty($response->json('data.last_refinement.changes'));
        $response->assertJsonPath('data.last_refinement.summary', 'No changes applied.');
    }

    public function test_participant_append_on_create_task_adds_warning(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->createTaskProposal($user);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Add Mario too']);

        $response->assertStatus(200);

        $warnings = $response->json('data.warnings');
        $this->assertNotEmpty($warnings);
        $this->assertContains(
            'One or more requested changes are not supported for this action type.',
            $warnings
        );
    }

    public function test_participant_append_on_create_task_proposal_status_unchanged(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->createTaskProposal($user);

        $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Add Mario too'])
            ->assertJsonPath('data.status', 'ready');

        $proposal->refresh();
        $this->assertEquals('ready', $proposal->status);
    }

    // ── 6. Existing valid refinement still works (regression) ─────────────────

    public function test_date_refinement_on_schedule_call_still_works(): void
    {
        $user = $this->actingAsUser();

        ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'schedule_call',
            'status' => 'draft',
            'confidence' => 0.7,
            'source_text' => 'Call Rossini',
            'entities' => ['lead' => 'Rossini'],
            'missing' => [
                ['key' => 'date', 'label' => 'Date', 'required' => true],
                ['key' => 'time', 'label' => 'Time', 'required' => true],
            ],
            'warnings' => [],
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossini', 'source' => 'detected', 'required' => true],
            ],
            'changes' => [
                ['type' => 'schedule', 'label' => 'Schedule Call', 'module' => 'calendar', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [],
        ]);

        $proposal = ActionProposal::latest()->first();

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Tomorrow morning']);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'ready');

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals(now()->addDay()->toDateString(), $fields['date']['value']);
        $this->assertEquals('09:00', $fields['time']['value']);
    }

    public function test_priority_refinement_on_schedule_call_still_works(): void
    {
        $user = $this->actingAsUser();

        $proposal = ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'schedule_call',
            'status' => 'ready',
            'confidence' => 0.85,
            'source_text' => 'Call Rossini',
            'entities' => ['lead' => 'Rossini'],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossini',                           'source' => 'detected', 'required' => true],
                ['key' => 'date', 'label' => 'Date', 'value' => now()->addDay()->toDateString(),      'source' => 'detected', 'required' => true],
                ['key' => 'time', 'label' => 'Time', 'value' => '09:00',                             'source' => 'detected', 'required' => true],
            ],
            'changes' => [
                ['type' => 'schedule', 'label' => 'Schedule Call', 'module' => 'calendar', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [],
        ]);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'High priority']);

        $response->assertStatus(200);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertArrayHasKey('priority', $fields->all());
        $this->assertEquals('high', $fields['priority']['value']);
    }

    public function test_participant_append_on_schedule_meeting_still_works(): void
    {
        $user = $this->actingAsUser();

        $proposal = ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'schedule_meeting',
            'status' => 'ready',
            'confidence' => 0.85,
            'source_text' => 'Meeting with Rossini',
            'entities' => ['lead' => 'Rossini'],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [
                ['key' => 'lead',         'label' => 'Lead',         'value' => 'Rossini',                      'source' => 'detected', 'required' => true],
                ['key' => 'date',         'label' => 'Date',         'value' => now()->addDay()->toDateString(), 'source' => 'detected', 'required' => true],
                ['key' => 'time',         'label' => 'Time',         'value' => '10:00',                        'source' => 'detected', 'required' => true],
                ['key' => 'participants', 'label' => 'Participants', 'value' => ['Luca'],                       'source' => 'detected', 'required' => false],
            ],
            'changes' => [
                ['type' => 'schedule', 'label' => 'Schedule Meeting', 'module' => 'calendar', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [],
        ]);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Add Mario too']);

        $response->assertStatus(200);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals(['Luca', 'Mario'], $fields['participants']['value']);
        $response->assertJsonPath('data.last_refinement.summary', 'Participant added.');
    }

    // ── 7. Ambiguity resolution still works for supported intents ─────────────

    public function test_ambiguity_resolution_works_for_schedule_call(): void
    {
        $user = $this->actingAsUser();

        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $r1->json('data.id');

        $this->assertEquals('schedule_call', $r1->json('data.intent'));
        $this->assertNotEmpty($r1->json('data.ambiguities'));

        $r2 = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Rossi SRL']);

        $r2->assertStatus(200);
        $this->assertEquals(7, $r2->json('data.ambiguities.0.selected_candidate_id'));

        $fields = collect($r2->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Rossi SRL', $fields['lead']['value']);
    }

    public function test_ambiguity_resolution_works_for_schedule_meeting(): void
    {
        $user = $this->actingAsUser();

        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Schedule a meeting with Rossi tomorrow morning']);
        $proposalId = $r1->json('data.id');

        $this->assertEquals('schedule_meeting', $r1->json('data.intent'));
        $this->assertNotEmpty($r1->json('data.ambiguities'));

        $r2 = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'Rossi SRL']);

        $r2->assertStatus(200)
            ->assertJsonPath('data.status', 'ready');

        $fields = collect($r2->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Rossi SRL', $fields['lead']['value']);
    }

    // ── 8. Ambiguity resolution for prepare_contract_from_quote ───────────────

    public function test_prepare_contract_supports_ambiguity_resolution_in_registry(): void
    {
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $capability = $registry->find('prepare_contract_from_quote');
        $this->assertNotNull($capability);
        $this->assertTrue($capability->supportsAmbiguityResolution);
        $this->assertContains(RefinementCapabilityType::ResolveAmbiguity, $capability->refinements);
    }

    public function test_prepare_contract_ambiguity_can_be_resolved_with_ordinal(): void
    {
        $user = $this->actingAsUser();

        $proposal = ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'prepare_contract_from_quote',
            'status' => 'draft',
            'confidence' => 0.75,
            'source_text' => 'Prepare a contract for Rossi',
            'entities' => [],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [],
            'changes' => [
                ['type' => 'create', 'label' => 'Prepare Contract', 'module' => 'tasks', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [
                [
                    'key' => 'lead',
                    'label' => 'Lead',
                    'reason' => 'multiple_matches',
                    'blocking' => true,
                    'query' => 'Rossi',
                    'selected_candidate_id' => null,
                    'candidates' => [
                        ['id' => 1, 'label' => 'Mario Rossi', 'type' => 'person',  'confidence' => 0.65],
                        ['id' => 7, 'label' => 'Rossi SRL',   'type' => 'company', 'confidence' => 0.80],
                    ],
                ],
            ],
        ]);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'The second one']);

        $response->assertStatus(200);
        $this->assertEquals(7, $response->json('data.ambiguities.0.selected_candidate_id'));

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Rossi SRL', $fields['lead']['value']);

        $warnings = $response->json('data.warnings') ?? [];
        $this->assertNotContains(
            'This action type does not support ambiguity resolution.',
            $warnings,
        );
    }

    // ── Registry unit: allowsMutation edge cases ──────────────────────────────

    public function test_unknown_intent_always_denies_mutation(): void
    {
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $mutation = new NormalizedMutation(
            field: 'date',
            label: 'Date',
            value: '2026-05-20',
            operation: 'replace',
        );

        $this->assertFalse($registry->allowsMutation('unknown_intent', $mutation));
    }

    public function test_assign_lead_allows_replace_on_lead(): void
    {
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $mutation = new NormalizedMutation(
            field: 'lead',
            label: 'Lead',
            value: 'Rossini',
            operation: 'replace',
        );

        $this->assertTrue($registry->allowsMutation('assign_lead', $mutation));
    }

    public function test_assign_lead_rejects_participant_append(): void
    {
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $mutation = new NormalizedMutation(
            field: 'participants',
            label: 'Participants',
            value: 'Mario',
            operation: 'append',
        );

        $this->assertFalse($registry->allowsMutation('assign_lead', $mutation));
    }

    public function test_schedule_call_allows_priority_clear(): void
    {
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $mutation = new NormalizedMutation(
            field: 'priority',
            label: 'Priority',
            value: null,
            operation: 'clear',
        );

        $this->assertTrue($registry->allowsMutation('schedule_call', $mutation));
    }

    public function test_prepare_contract_rejects_priority_replace(): void
    {
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $mutation = new NormalizedMutation(
            field: 'priority',
            label: 'Priority',
            value: 'high',
            operation: 'replace',
        );

        $this->assertFalse($registry->allowsMutation('prepare_contract_from_quote', $mutation));
    }

    public function test_refinement_capability_types_declared_for_schedule_meeting(): void
    {
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $capability = $registry->find('schedule_meeting');
        $this->assertNotNull($capability);

        $types = $capability->refinements;
        $this->assertContains(RefinementCapabilityType::AppendCollectionItem, $types);
        $this->assertContains(RefinementCapabilityType::RemoveCollectionItem, $types);
        $this->assertContains(RefinementCapabilityType::ReplaceCollectionItem, $types);
        $this->assertContains(RefinementCapabilityType::ResolveAmbiguity, $types);
        $this->assertContains(RefinementCapabilityType::ContextualReference, $types);
    }

    // ── 9. Ambiguity warning is emitted when intent does not support resolution ─
    //
    // NB: every REGISTERED intent that can produce a blocking lead ambiguity now
    // supports resolving it (that is the architectural invariant). The
    // not-supported branch therefore only applies to an unregistered/unknown intent
    // (null capability → deny-by-default), which these tests exercise with a crafted
    // proposal carrying a synthetic blocking ambiguity.

    /**
     * When a proposal has a blocking ambiguity and the intent does not support
     * ambiguity resolution (here: an unregistered intent), a refinement attempt must:
     * - add the 'ambiguity_resolution_not_supported' warning
     * - leave the ambiguity unresolved
     * - not throw
     */
    public function test_ambiguity_resolution_not_supported_warning_is_emitted(): void
    {
        $user = $this->actingAsUser();

        $proposal = ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'archive_record',
            'status' => 'draft',
            'confidence' => 0.9,
            'source_text' => 'Create a task for Rossi',
            'entities' => [],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [],
            'changes' => [
                ['type' => 'create', 'label' => 'Create Task', 'module' => 'tasks', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [
                [
                    'key' => 'lead',
                    'label' => 'Lead',
                    'reason' => 'multiple_matches',
                    'blocking' => true,
                    'query' => 'Rossi',
                    'selected_candidate_id' => null,
                    'candidates' => [
                        ['id' => 1, 'label' => 'Mario Rossi', 'type' => 'person',  'confidence' => 0.65],
                        ['id' => 7, 'label' => 'Rossi SRL',   'type' => 'company', 'confidence' => 0.80],
                    ],
                ],
            ],
        ]);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Rossi SRL']);

        $response->assertStatus(200);

        // Warning must be present
        $warnings = $response->json('data.warnings');
        $this->assertNotEmpty($warnings);
        $this->assertContains(
            'This action type does not support ambiguity resolution.',
            $warnings
        );

        // Ambiguity must remain unresolved (proposal not mutated via resolution path)
        $this->assertNull($response->json('data.ambiguities.0.selected_candidate_id'));
    }

    /**
     * After the 'ambiguity_resolution_not_supported' warning is added, the proposal
     * must be continuable: status is preserved, no exception is thrown.
     */
    public function test_ambiguity_resolution_not_supported_preserves_proposal_continuity(): void
    {
        $user = $this->actingAsUser();

        $proposal = ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'archive_record',
            'status' => 'draft',
            'confidence' => 0.9,
            'source_text' => 'Create a task for Rossi',
            'entities' => [],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [],
            'changes' => [
                ['type' => 'create', 'label' => 'Create Task', 'module' => 'tasks', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [
                [
                    'key' => 'lead',
                    'label' => 'Lead',
                    'reason' => 'multiple_matches',
                    'blocking' => true,
                    'query' => 'Rossi',
                    'selected_candidate_id' => null,
                    'candidates' => [
                        ['id' => 1, 'label' => 'Mario Rossi', 'type' => 'person',  'confidence' => 0.65],
                        ['id' => 7, 'label' => 'Rossi SRL',   'type' => 'company', 'confidence' => 0.80],
                    ],
                ],
            ],
        ]);

        // A clarification attempt on an intent that cannot resolve ambiguity: the
        // proposal must stay continuable (status preserved, no exception).
        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Rossi SRL']);

        $response->assertStatus(200);

        // Status must be preserved — not errored, not advanced past blocking ambiguity
        $this->assertEquals('draft', $response->json('data.status'));

        // 'ambiguity_resolution_not_supported' warning is present
        $this->assertContains(
            'This action type does not support ambiguity resolution.',
            $response->json('data.warnings')
        );
    }

    // ── 10. Ambiguity resolution regression for supported intents ─────────────

    /**
     * Regression: ambiguity resolution for assign_lead (supported intent) must
     * still work correctly after the warning path was introduced.
     */
    public function test_ambiguity_resolution_works_for_assign_lead(): void
    {
        $user = $this->actingAsUser();

        $proposal = ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'assign_lead',
            'status' => 'draft',
            'confidence' => 0.8,
            'source_text' => 'Assign Rossi to Giulia',
            'entities' => [],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [
                ['key' => 'assignee', 'label' => 'Assignee', 'value' => 'Giulia', 'source' => 'detected', 'required' => true],
            ],
            'changes' => [
                ['type' => 'assign', 'label' => 'Assign Lead', 'module' => 'leads', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [
                [
                    'key' => 'lead',
                    'label' => 'Lead',
                    'reason' => 'multiple_matches',
                    'blocking' => true,
                    'query' => 'Rossi',
                    'selected_candidate_id' => null,
                    'candidates' => [
                        ['id' => 1, 'label' => 'Mario Rossi', 'type' => 'person',  'confidence' => 0.65],
                        ['id' => 7, 'label' => 'Rossi SRL',   'type' => 'company', 'confidence' => 0.80],
                    ],
                ],
            ],
        ]);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Rossi SRL']);

        $response->assertStatus(200);

        // Ambiguity must be resolved
        $this->assertEquals(7, $response->json('data.ambiguities.0.selected_candidate_id'));

        // No 'ambiguity_resolution_not_supported' warning must have been added
        $warnings = $response->json('data.warnings') ?? [];
        $this->assertNotContains(
            'This action type does not support ambiguity resolution.',
            $warnings
        );
    }

    // ── 12. Proposal API response includes capabilities ───────────────────────

    public function test_proposal_api_response_includes_capabilities_key(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Create a task for tomorrow']);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'capabilities' => [
                    'supports_contextual_references',
                    'supports_ambiguity_resolution',
                    'supports_collection_mutations',
                    'mutations',
                    'refinements',
                ],
            ],
        ]);
    }

    public function test_schedule_meeting_proposal_returns_supports_collection_mutations_true(): void
    {
        $user = $this->actingAsUser();

        $proposal = ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'schedule_meeting',
            'status' => 'ready',
            'confidence' => 0.85,
            'source_text' => 'Meeting with Rossini tomorrow',
            'entities' => ['lead' => 'Rossini'],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossini', 'source' => 'detected', 'required' => true],
            ],
            'changes' => [
                ['type' => 'schedule', 'label' => 'Schedule Meeting', 'module' => 'calendar', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [],
        ]);

        // Refine to get back a full resource response that includes capabilities
        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'High priority']);

        $response->assertStatus(200);
        $response->assertJsonPath('data.capabilities.supports_collection_mutations', true);
    }

    public function test_create_task_proposal_returns_supports_collection_mutations_false(): void
    {
        $user = $this->actingAsUser();

        // Use the interpret endpoint — it returns an ActionProposalResource directly
        $response = $this->postJson('/api/actions/interpret', ['text' => 'Create a task for tomorrow']);

        $response->assertStatus(200);
        $response->assertJsonPath('data.intent', 'create_task');
        $response->assertJsonPath('data.capabilities.supports_collection_mutations', false);
    }

    // ── Capability labels (localized payload) ─────────────────────────────────

    public function test_capabilities_payload_includes_localized_labels_in_english(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(
            '/api/actions/interpret',
            ['text' => 'Schedule a meeting with Rossi SRL tomorrow at 10am'],
            ['Accept-Language' => 'en'],
        );

        $response->assertStatus(200);
        $response->assertJsonPath('data.intent', 'schedule_meeting');

        $refinements = collect($response->json('data.capabilities.refinements'));
        $append = $refinements->firstWhere('key', 'append_collection_item');

        $this->assertNotNull($append);
        $this->assertEquals('Add participants', $append['label']);

        $mutations = collect($response->json('data.capabilities.mutations'));
        $appendMutation = $mutations->firstWhere('operation', 'append');
        $this->assertNotNull($appendMutation);
        $this->assertEquals('Add', $appendMutation['operation_label']);

        $field = collect($appendMutation['fields'])->firstWhere('key', 'participants');
        $this->assertNotNull($field);
        $this->assertEquals('Participants', $field['label']);
    }

    public function test_capabilities_payload_uses_italian_when_accept_language_is_it(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(
            '/api/actions/interpret',
            ['text' => 'Schedule a meeting with Rossi SRL tomorrow at 10am'],
            ['Accept-Language' => 'it-IT'],
        );

        $response->assertStatus(200);

        $refinements = collect($response->json('data.capabilities.refinements'));
        $append = $refinements->firstWhere('key', 'append_collection_item');
        $this->assertNotNull($append);
        $this->assertEquals('Aggiungi partecipanti', $append['label']);

        $mutations = collect($response->json('data.capabilities.mutations'));
        $appendMutation = $mutations->firstWhere('operation', 'append');
        $this->assertNotNull($appendMutation);
        $this->assertEquals('Aggiungi', $appendMutation['operation_label']);
    }

    public function test_capabilities_payload_preserves_technical_keys(): void
    {
        $this->actingAsUser();

        $response = $this->postJson(
            '/api/actions/interpret',
            ['text' => 'Schedule a meeting with Rossi SRL tomorrow at 10am'],
            ['Accept-Language' => 'it'],
        );

        $response->assertStatus(200);

        $refinements = $response->json('data.capabilities.refinements');
        $this->assertNotEmpty($refinements);
        foreach ($refinements as $r) {
            $this->assertArrayHasKey('key', $r);
            $this->assertArrayHasKey('label', $r);
        }

        $mutations = $response->json('data.capabilities.mutations');
        $this->assertNotEmpty($mutations);
        foreach ($mutations as $m) {
            $this->assertArrayHasKey('operation', $m);
            $this->assertArrayHasKey('operation_label', $m);
            $this->assertArrayHasKey('fields', $m);
            $this->assertArrayHasKey('collection', $m);
            foreach ($m['fields'] as $f) {
                $this->assertArrayHasKey('key', $f);
                $this->assertArrayHasKey('label', $f);
            }
        }
    }

    public function test_persistence_service_does_not_persist_capabilities(): void
    {
        $user = $this->actingAsUser();

        $proposal = $this->createTaskProposal($user);

        // The persisted model must not have a 'capabilities' attribute
        $this->assertArrayNotHasKey('capabilities', $proposal->getAttributes());

        // Capabilities must not appear in the raw DB record
        $fresh = ActionProposal::find($proposal->id);
        $this->assertArrayNotHasKey('capabilities', $fresh->getAttributes());
    }

    // ── 11. DefaultIntentCapabilities covers all 5 known intents ─────────────

    // ── Warning deduplication ─────────────────────────────────────────────────

    public function test_repeated_unsupported_mutation_does_not_duplicate_warning(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->createTaskProposal($user);

        $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Add Mario too'])->assertStatus(200);
        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Add Luca too']);

        $response->assertStatus(200);

        $warnings = $response->json('data.warnings') ?? [];
        $occurrences = array_filter(
            $warnings,
            fn (string $w) => $w === 'One or more requested changes are not supported for this action type.',
        );

        $this->assertCount(1, $occurrences);
    }

    public function test_repeated_unsupported_ambiguity_resolution_does_not_duplicate_warning(): void
    {
        $user = $this->actingAsUser();

        $proposal = ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'archive_record',
            'status' => 'draft',
            'confidence' => 0.9,
            'source_text' => 'Create a task for Rossi',
            'entities' => [],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [],
            'changes' => [
                ['type' => 'create', 'label' => 'Create Task', 'module' => 'tasks', 'payload' => []],
            ],
            'needs_confirmation' => true,
            'ambiguities' => [
                [
                    'key' => 'lead',
                    'label' => 'Lead',
                    'reason' => 'multiple_matches',
                    'blocking' => true,
                    'query' => 'Rossi',
                    'selected_candidate_id' => null,
                    'candidates' => [
                        ['id' => 1, 'label' => 'Mario Rossi', 'type' => 'person',  'confidence' => 0.65],
                        ['id' => 7, 'label' => 'Rossi SRL',   'type' => 'company', 'confidence' => 0.80],
                    ],
                ],
            ],
        ]);

        $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Rossi SRL'])->assertStatus(200);
        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Rossi SRL']);

        $response->assertStatus(200);

        $warnings = $response->json('data.warnings') ?? [];
        $occurrences = array_filter(
            $warnings,
            fn (string $w) => $w === 'This action type does not support ambiguity resolution.',
        );

        $this->assertCount(1, $occurrences);
    }

    public function test_capability_registry_returns_expected_capabilities_for_all_five_intents(): void
    {
        /** @var IntentCapabilityRegistry $registry */
        $registry = $this->app->make(IntentCapabilityRegistry::class);

        $expectedIntents = [
            'create_task',
            'schedule_call',
            'schedule_meeting',
            'assign_lead',
            'prepare_contract_from_quote',
        ];

        foreach ($expectedIntents as $intent) {
            $capability = $registry->find($intent);
            $this->assertNotNull($capability, "Expected capability for intent [{$intent}] to be registered.");
            $this->assertEquals($intent, $capability->intent);
            $this->assertNotEmpty($capability->mutations, "Intent [{$intent}] must declare at least one mutation capability.");
            $this->assertNotEmpty($capability->refinements, "Intent [{$intent}] must declare at least one refinement capability type.");
        }
    }
}
