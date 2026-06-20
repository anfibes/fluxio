<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Carbon\Carbon;
use Fluxio\Actions\Contracts\ActionExecutorInterface;
use Fluxio\Actions\DTO\Execution\ExecutionResult;
use Fluxio\Actions\Executors\CreateTaskActionExecutor;
use Fluxio\Actions\Http\Resources\ActionProposalResource;
use Fluxio\Actions\Models\ActionProposal;
use Fluxio\Leads\Models\Lead;
use Fluxio\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class ExecuteActionProposalTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function interpretAndConfirm(string $text): array
    {
        $interpret = $this->postJson('/api/actions/interpret', ['text' => $text]);
        $interpret->assertStatus(200);

        $proposalId = $interpret->json('data.id');

        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);

        return ['id' => $proposalId];
    }

    // --- happy path ---

    public function test_confirmed_create_task_proposal_executes_and_creates_task(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');

        $response = $this->postJson("/api/actions/{$proposal['id']}/execute");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertNotNull($response->json('data.executed_at'));
        $this->assertEquals('Task created successfully.', $response->json('data.execution_result.summary'));
        $this->assertNotNull($response->json('data.execution_result.details.resource_id'));
        $this->assertEquals('tasks', $response->json('data.execution_result.details.module'));
        $this->assertEquals('created', $response->json('data.execution_result.details.action'));

        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_execute_returns_executed_message(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');

        $this->postJson("/api/actions/{$proposal['id']}/execute")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Action executed successfully.');
    }

    // --- idempotency ---

    public function test_execute_is_idempotent(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');

        $first = $this->postJson("/api/actions/{$proposal['id']}/execute");
        $firstResult = $first->json('data.execution_result');

        $second = $this->postJson("/api/actions/{$proposal['id']}/execute");
        $secondResult = $second->json('data.execution_result');

        $second->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertEquals($firstResult, $secondResult);
        $this->assertDatabaseCount('tasks', 1);
    }

    // --- lifecycle guards ---

    public function test_draft_proposal_cannot_be_executed(): void
    {
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', [
            'text' => 'Schedule a call with Rossini',
        ]);
        $interpret->assertStatus(200);

        $this->postJson("/api/actions/{$interpret->json('data.id')}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['proposal']]);
    }

    public function test_ready_proposal_cannot_be_executed(): void
    {
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', [
            'text' => 'Create a task for Rossini',
        ]);
        $interpret->assertStatus(200);
        $this->assertEquals('ready', $interpret->json('data.status'));

        $this->postJson("/api/actions/{$interpret->json('data.id')}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['proposal']]);
    }

    // --- ambiguous lead ---

    public function test_ambiguous_lead_is_blocking_at_proposal_time_and_creates_no_task(): void
    {
        $this->actingAsUser();

        Lead::factory()->create(['name' => 'Rossini']);
        Lead::factory()->create(['name' => 'Rossini']);

        // With the DB-backed LeadEntityResolver, two matching leads now surface a
        // BLOCKING ambiguity at proposal time (deterministic), rather than only failing
        // later at execution against the DB. The proposal stays draft, is never
        // confirmable, and no task is created.
        $interpret = $this->postJson('/api/actions/interpret', ['text' => 'Create a task for Rossini']);
        $interpret->assertStatus(200)
            ->assertJsonPath('data.status', 'draft');

        $ambiguity = collect($interpret->json('data.ambiguities'))->firstWhere('key', 'lead');
        $this->assertNotNull($ambiguity);
        $this->assertTrue($ambiguity['blocking']);
        $this->assertNull($ambiguity['selected_candidate_id']);

        $proposalId = $interpret->json('data.id');

        // A draft proposal can neither be confirmed nor executed.
        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(422);
        $this->postJson("/api/actions/{$proposalId}/execute")->assertStatus(422);

        $this->assertDatabaseCount('tasks', 0);

        // No failure state is recorded; the proposal simply never left draft.
        $refreshed = ActionProposal::find($proposalId);
        $this->assertEquals('draft', $refreshed->status);
        $this->assertNull($refreshed->failed_at);
    }

    // --- zero leads ---

    public function test_no_matching_lead_creates_task_without_lead_id(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');

        $this->postJson("/api/actions/{$proposal['id']}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseCount('tasks', 1);
        $this->assertDatabaseHas('tasks', ['lead_id' => null]);
    }

    // --- ownership ---

    public function test_user_cannot_execute_another_users_proposal(): void
    {
        $userA = User::factory()->create();
        Sanctum::actingAs($userA);

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');
        $proposalId = $proposal['id'];

        $userB = User::factory()->create();
        Sanctum::actingAs($userB);

        $this->postJson("/api/actions/{$proposalId}/execute")
            ->assertStatus(404);
    }

    // --- lead resolution ---

    public function test_single_matching_lead_is_linked_to_created_task(): void
    {
        $this->actingAsUser();

        $lead = Lead::factory()->create(['name' => 'Rossini']);

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');

        $this->postJson("/api/actions/{$proposal['id']}/execute")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'executed');

        $this->assertDatabaseHas('tasks', ['lead_id' => $lead->id]);
    }

    // --- terminal status guard ---

    public function test_failed_proposal_cannot_be_executed(): void
    {
        $user = $this->actingAsUser();

        $proposal = ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'create_task',
            'status' => 'failed',
            'confidence' => 0.9,
            'source_text' => 'Create a task for Rossini',
            'entities' => ['lead' => 'Rossini'],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [],
            'changes' => [],
            'needs_confirmation' => true,
        ]);

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['proposal']]);

        $this->assertDatabaseHas('action_proposals', [
            'id' => $proposal->id,
            'status' => 'failed',
        ]);
    }

    // --- unsupported intent ---

    public function test_unsupported_intent_marks_proposal_as_failed(): void
    {
        $user = $this->actingAsUser();

        // This negative path intentionally re-throws the "no executor" RuntimeException
        // so the request returns 500. Fake reporting for ONLY that exception type so its
        // expected stack trace does not pollute laravel.log; rendering (the 500) still
        // delegates to the real handler, and we assert below it was reported so the path
        // is still proven to have fired. Scoped to this test; no runtime change.
        Exceptions::fake([RuntimeException::class]);

        $proposal = ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'unsupported_intent',
            'status' => 'confirmed',
            'confidence' => 0.5,
            'source_text' => 'Do something unusual',
            'entities' => [],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [],
            'changes' => [],
            'needs_confirmation' => true,
        ]);

        $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(500);

        $refreshed = $proposal->fresh();
        $this->assertEquals('failed', $refreshed->status);
        $this->assertNotNull($refreshed->failed_at);
        $this->assertNotNull($refreshed->failure_reason);

        // Failure is typed + sanitized: the raw exception message (which names the
        // unsupported intent) never reaches persisted proposal state.
        $this->assertEquals('unsupported_intent', $refreshed->failure_reason_code);
        $this->assertEquals(
            __('actions::actions.execution_failure.unsupported_intent'),
            $refreshed->failure_reason,
        );
        $this->assertStringNotContainsString('unsupported_intent', $refreshed->failure_reason);
        $this->assertStringNotContainsString('executor', $refreshed->failure_reason);

        // The typed failure surface (Phase 9B) is exposed by the resource — a closed
        // reason code plus the sanitized message, branchable without parsing prose.
        $payload = (new ActionProposalResource($refreshed))->resolve();
        $this->assertSame('unsupported_intent', $payload['execution_failure']['reason']);
        $this->assertSame($refreshed->failure_reason, $payload['execution_failure']['message']);

        $this->assertDatabaseCount('tasks', 0);

        // The negative path still reported the exception (it was suppressed from the
        // log, not swallowed) — proving the re-throw / 500 behavior is unchanged.
        Exceptions::assertReported(RuntimeException::class);
    }

    // --- generic executor throwable ---

    public function test_executor_throwable_marks_proposal_failed_with_execution_failed_reason(): void
    {
        $this->actingAsUser();

        // Expected negative path: the executor re-throws, producing a 500. Fake reporting
        // for ONLY RuntimeException so the raw message does not pollute laravel.log;
        // rendering still delegates to the real handler (500 preserved) and we assert
        // below it was reported. Scoped to this test; no runtime change.
        Exceptions::fake([RuntimeException::class]);

        // Registered intent, but its executor raises an unexpected error carrying a
        // raw, sensitive message. The reason must be `execution_failed` and the raw
        // message must never reach proposal state.
        $this->app->bind(CreateTaskActionExecutor::class, fn () => new class implements ActionExecutorInterface
        {
            public function execute(ActionProposal $proposal): ExecutionResult
            {
                throw new \RuntimeException('RAW_SECRET_DB_ERROR at line 42');
            }
        });

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');

        $this->postJson("/api/actions/{$proposal['id']}/execute")->assertStatus(500);

        $refreshed = ActionProposal::find($proposal['id']);
        $this->assertEquals('failed', $refreshed->status);
        $this->assertNotNull($refreshed->failed_at);
        $this->assertEquals('execution_failed', $refreshed->failure_reason_code);
        $this->assertEquals(
            __('actions::actions.execution_failure.execution_failed'),
            $refreshed->failure_reason,
        );
        $this->assertStringNotContainsString('RAW_SECRET_DB_ERROR', (string) $refreshed->failure_reason);

        $payload = (new ActionProposalResource($refreshed))->resolve();
        $this->assertSame('execution_failed', $payload['execution_failure']['reason']);
        $this->assertSame($refreshed->failure_reason, $payload['execution_failure']['message']);

        $this->assertDatabaseCount('tasks', 0);

        // Suppressed from the log, not swallowed — the re-throw / 500 path still fired.
        Exceptions::assertReported(RuntimeException::class);
    }

    // --- typed failure surface: null when not failed ---

    public function test_execution_failure_surface_is_null_when_not_failed(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');

        $response = $this->postJson("/api/actions/{$proposal['id']}/execute");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.execution_failure', null);

        $this->assertNull(ActionProposal::find($proposal['id'])->failure_reason_code);
    }

    // --- atomic failure path: partial side effects roll back, failed persists ---

    public function test_executor_partial_side_effects_roll_back_while_failed_state_persists(): void
    {
        $this->actingAsUser();

        // Expected negative path: the executor re-throws, producing a 500. Fake reporting
        // for ONLY RuntimeException so its message does not pollute laravel.log; rendering
        // still delegates to the real handler (500 preserved). Scoped to this test.
        Exceptions::fake([RuntimeException::class]);

        // Executor creates a domain row, THEN throws. The created Task must roll back
        // (inner savepoint) while the proposal commits as `failed` under the same row
        // lock — so a concurrent execute can never observe `confirmed` after rollback.
        $this->app->bind(CreateTaskActionExecutor::class, fn () => new class implements ActionExecutorInterface
        {
            public function execute(ActionProposal $proposal): ExecutionResult
            {
                Task::create([
                    'title' => 'partial side effect',
                    'description' => null,
                    'status' => 'pending',
                    'priority' => 'normal',
                    'due_at' => null,
                    'lead_id' => null,
                ]);

                throw new \RuntimeException('boom after partial side effect');
            }
        });

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');

        $this->postJson("/api/actions/{$proposal['id']}/execute")->assertStatus(500);

        // Partial side effect was rolled back ...
        $this->assertDatabaseCount('tasks', 0);

        // ... yet the terminal failed state was committed (under the held lock).
        $refreshed = ActionProposal::find($proposal['id']);
        $this->assertEquals('failed', $refreshed->status);
        $this->assertNotNull($refreshed->failed_at);
        $this->assertEquals('execution_failed', $refreshed->failure_reason_code);
        $this->assertStringNotContainsString('boom', (string) $refreshed->failure_reason);

        // Suppressed from the log, not swallowed — the re-throw / 500 path still fired.
        Exceptions::assertReported(RuntimeException::class);
    }

    // --- resource guard: failure surface only when actually failed ---

    public function test_execution_failure_surface_is_null_when_status_not_failed_even_with_code(): void
    {
        $user = $this->actingAsUser();

        // Defensive: a stale code on a non-failed proposal must never surface a failure.
        $proposal = ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'create_task',
            'status' => 'executed',
            'confidence' => 0.9,
            'source_text' => 'Create a task for Rossini',
            'entities' => [],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [],
            'changes' => [],
            'needs_confirmation' => true,
            'failure_reason' => 'stale message',
            'failure_reason_code' => 'execution_failed',
        ]);

        $payload = (new ActionProposalResource($proposal))->resolve();

        $this->assertNull($payload['execution_failure']);
    }

    // --- persistence ---

    public function test_execution_result_is_stored_in_database(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');

        $response = $this->postJson("/api/actions/{$proposal['id']}/execute");
        $response->assertStatus(200);

        $refreshed = ActionProposal::find($proposal['id']);
        $result = $refreshed->execution_result;

        $this->assertNotNull($result);
        $this->assertEquals('Task created successfully.', $result['summary']);
        $this->assertEquals('tasks', $result['details']['module']);
        $this->assertEquals('created', $result['details']['action']);
        $this->assertEquals('task', $result['details']['resource_type']);
        $this->assertNotNull($result['details']['resource_id']);
    }

    public function test_executed_at_is_stored_in_database(): void
    {
        $this->actingAsUser();

        $proposal = $this->interpretAndConfirm('Create a task for Rossini');

        $this->postJson("/api/actions/{$proposal['id']}/execute")->assertStatus(200);

        $refreshed = ActionProposal::find($proposal['id']);
        $this->assertNotNull($refreshed->executed_at);
    }

    // --- due_at propagation ---

    public function test_create_task_with_date_and_time_propagates_due_at(): void
    {
        Carbon::setTestNow('2026-06-20 12:00:00');
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', [
            'text' => 'Create a task for Rossini tomorrow at 10am',
        ]);

        $interpret->assertStatus(200)
            ->assertJsonPath('data.intent', 'create_task');

        // due_at appears in editable_fields
        $fields = collect($interpret->json('data.editable_fields'))->keyBy('key');
        $this->assertArrayHasKey('date', $fields->all());
        $this->assertArrayHasKey('time', $fields->all());
        $this->assertArrayHasKey('due_at', $fields->all());
        $this->assertEquals('2026-06-21', $fields['date']['value']);
        $this->assertEquals('10:00', $fields['time']['value']);
        $this->assertEquals('2026-06-21 10:00', $fields['due_at']['value']);

        // due_at appears in changes.payload
        $payload = $interpret->json('data.changes.0.payload');
        $this->assertArrayHasKey('date', $payload);
        $this->assertArrayHasKey('time', $payload);
        $this->assertArrayHasKey('due_at', $payload);
        $this->assertEquals('2026-06-21 10:00', $payload['due_at']);

        // Execution persists due_at on the Task
        $proposalId = $interpret->json('data.id');
        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);
        $this->postJson("/api/actions/{$proposalId}/execute")->assertStatus(200);

        $this->assertDatabaseCount('tasks', 1);
        $task = Task::first();
        $this->assertNotNull($task->due_at);
        $this->assertEquals('2026-06-21 10:00:00', $task->due_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_create_task_with_date_only_propagates_due_at(): void
    {
        Carbon::setTestNow('2026-06-20 12:00:00');
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', [
            'text' => 'Create a task for Rossini tomorrow',
        ]);

        $interpret->assertStatus(200);

        $fields = collect($interpret->json('data.editable_fields'))->keyBy('key');
        $this->assertArrayHasKey('date', $fields->all());
        $this->assertArrayHasKey('due_at', $fields->all());
        $this->assertArrayNotHasKey('time', $fields->all());
        $this->assertEquals('2026-06-21', $fields['due_at']['value']);

        $payload = $interpret->json('data.changes.0.payload');
        $this->assertEquals('2026-06-21', $payload['due_at']);

        // Execution
        $proposalId = $interpret->json('data.id');
        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);
        $this->postJson("/api/actions/{$proposalId}/execute")->assertStatus(200);

        $task = Task::first();
        $this->assertNotNull($task->due_at);
        $this->assertEquals('2026-06-21 00:00:00', $task->due_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_create_task_without_date_has_no_due_at(): void
    {
        $this->actingAsUser();

        $interpret = $this->postJson('/api/actions/interpret', [
            'text' => 'Create a task for Rossini',
        ]);

        $interpret->assertStatus(200);

        $fields = collect($interpret->json('data.editable_fields'))->keyBy('key');
        $this->assertArrayNotHasKey('date', $fields->all());
        $this->assertArrayNotHasKey('time', $fields->all());
        $this->assertArrayNotHasKey('due_at', $fields->all());

        $payload = $interpret->json('data.changes.0.payload');
        $this->assertArrayNotHasKey('due_at', $payload);

        // Execution still works, just no due date
        $proposalId = $interpret->json('data.id');
        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);
        $this->postJson("/api/actions/{$proposalId}/execute")->assertStatus(200);

        $task = Task::first();
        $this->assertNull($task->due_at);
    }

    // --- due_at refinement compatibility ---

    public function test_create_task_due_at_updated_by_temporal_refinement(): void
    {
        Carbon::setTestNow('2026-06-20 12:00:00');
        $this->actingAsUser();

        // Create a task with initial due date
        $interpret = $this->postJson('/api/actions/interpret', [
            'text' => 'Create a task tomorrow at 10am',
        ]);

        $interpret->assertStatus(200);
        $proposalId = $interpret->json('data.id');
        $this->assertEquals('2026-06-21 10:00', $interpret->json('data.changes.0.payload.due_at'));

        // Refine: "move it to the day after tomorrow at 3pm"
        // 2026-06-20 + 2 days = 2026-06-22; "at 3pm" → 15:00
        $refine = $this->postJson("/api/actions/{$proposalId}/refine", [
            'text' => 'move it to the day after tomorrow at 3pm',
        ]);

        $refine->assertStatus(200);

        // due_at in payload is updated
        $this->assertEquals('2026-06-22 15:00', $refine->json('data.changes.0.payload.due_at'));
        $this->assertEquals('2026-06-22', $refine->json('data.changes.0.payload.date'));
        $this->assertEquals('15:00', $refine->json('data.changes.0.payload.time'));

        // Execution persists the updated due_at
        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);
        $this->postJson("/api/actions/{$proposalId}/execute")->assertStatus(200);

        $task = Task::first();
        $this->assertNotNull($task->due_at);
        $this->assertEquals('2026-06-22 15:00:00', $task->due_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_create_task_due_at_stays_coherent_when_only_date_is_refined(): void
    {
        Carbon::setTestNow('2026-06-20 12:00:00');
        $this->actingAsUser();

        // Create a task with date and time
        $interpret = $this->postJson('/api/actions/interpret', [
            'text' => 'Create a task tomorrow at 10am',
        ]);

        $interpret->assertStatus(200);
        $proposalId = $interpret->json('data.id');
        $this->assertEquals('2026-06-21 10:00', $interpret->json('data.changes.0.payload.due_at'));

        // Refine only the date — "move it to the day after tomorrow"
        // Parser extracts date=2026-06-22, no time → old time=10:00 stays
        $refine = $this->postJson("/api/actions/{$proposalId}/refine", [
            'text' => 'move it to the day after tomorrow',
        ]);

        $refine->assertStatus(200);

        // due_at merges new date (2026-06-22) with old time (10:00)
        $this->assertEquals('2026-06-22 10:00', $refine->json('data.changes.0.payload.due_at'));
        $this->assertEquals('2026-06-22', $refine->json('data.changes.0.payload.date'));
        $this->assertEquals('10:00', $refine->json('data.changes.0.payload.time'));

        // due_at is present in editable_fields
        $fields = collect($refine->json('data.editable_fields'))->keyBy('key');
        $this->assertArrayHasKey('due_at', $fields->all());
        $this->assertEquals('2026-06-22 10:00', $fields['due_at']['value']);

        // Execution persists the correct due_at
        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);
        $this->postJson("/api/actions/{$proposalId}/execute")->assertStatus(200);

        $task = Task::first();
        $this->assertNotNull($task->due_at);
        $this->assertEquals('2026-06-22 10:00:00', $task->due_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    // --- due_at injected via refinement (proposal originally had no date) ---

    public function test_due_at_injected_when_temporal_info_added_via_refinement(): void
    {
        Carbon::setTestNow('2026-06-20 12:00:00');
        $this->actingAsUser();

        // 1. Create a task without any temporal info
        $interpret = $this->postJson('/api/actions/interpret', [
            'text' => 'Create a task for Mario Rossi',
        ]);

        $interpret->assertStatus(200)
            ->assertJsonPath('data.intent', 'create_task');

        $proposalId = $interpret->json('data.id');

        // No date/time/due_at anywhere
        $fields = collect($interpret->json('data.editable_fields'))->keyBy('key');
        $this->assertArrayNotHasKey('date', $fields->all());
        $this->assertArrayNotHasKey('time', $fields->all());
        $this->assertArrayNotHasKey('due_at', $fields->all());
        $this->assertArrayNotHasKey('due_at', $interpret->json('data.changes.0.payload'));

        // 2. Refine to add temporal info
        $refine = $this->postJson("/api/actions/{$proposalId}/refine", [
            'text' => 'Tomorrow at 10am',
        ]);

        $refine->assertStatus(200);

        // date and time appear in editable_fields
        $refinedFields = collect($refine->json('data.editable_fields'))->keyBy('key');
        $this->assertArrayHasKey('date', $refinedFields->all());
        $this->assertArrayHasKey('time', $refinedFields->all());
        $this->assertEquals('2026-06-21', $refinedFields['date']['value']);
        $this->assertEquals('10:00', $refinedFields['time']['value']);

        // due_at is INJECTED into both editable_fields and changes.payload
        $this->assertArrayHasKey('due_at', $refinedFields->all());
        $this->assertEquals('2026-06-21 10:00', $refinedFields['due_at']['value']);
        $this->assertArrayHasKey('due_at', $refine->json('data.changes.0.payload'));
        $this->assertEquals('2026-06-21 10:00', $refine->json('data.changes.0.payload.due_at'));

        // 3. Execution persists due_at
        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);
        $this->postJson("/api/actions/{$proposalId}/execute")->assertStatus(200);

        $task = Task::first();
        $this->assertNotNull($task->due_at);
        $this->assertEquals('2026-06-21 10:00:00', $task->due_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    public function test_due_at_remains_coherent_across_incremental_temporal_refinements(): void
    {
        Carbon::setTestNow('2026-06-20 12:00:00');
        $this->actingAsUser();

        // 1. Create a task with no temporal info
        $interpret = $this->postJson('/api/actions/interpret', [
            'text' => 'Create a task for Mario Rossi',
        ]);

        $proposalId = $interpret->json('data.id');
        $this->assertArrayNotHasKey('due_at', $interpret->json('data.changes.0.payload'));

        // 2. Refine: add date only
        $r1 = $this->postJson("/api/actions/{$proposalId}/refine", [
            'text' => 'Tomorrow',
        ]);

        $r1->assertStatus(200);

        // due_at injected into payload (date only, no time yet)
        $this->assertArrayHasKey('due_at', $r1->json('data.changes.0.payload'));
        $this->assertEquals('2026-06-21', $r1->json('data.changes.0.payload.due_at'));

        // date and due_at in editable_fields; time not yet present
        $r1fields = collect($r1->json('data.editable_fields'))->keyBy('key');
        $this->assertArrayHasKey('date', $r1fields->all());
        $this->assertArrayHasKey('due_at', $r1fields->all());
        $this->assertArrayNotHasKey('time', $r1fields->all());
        $this->assertEquals('2026-06-21', $r1fields['due_at']['value']);

        // 3. Refine: add time
        $r2 = $this->postJson("/api/actions/{$proposalId}/refine", [
            'text' => 'At 10am',
        ]);

        $r2->assertStatus(200);
        $this->assertArrayHasKey('due_at', $r2->json('data.changes.0.payload'));
        $this->assertEquals('2026-06-21 10:00', $r2->json('data.changes.0.payload.due_at'));

        // date, time, and due_at all present in editable_fields
        $fields = collect($r2->json('data.editable_fields'))->keyBy('key');
        $this->assertArrayHasKey('date', $fields->all());
        $this->assertArrayHasKey('time', $fields->all());
        $this->assertArrayHasKey('due_at', $fields->all());
        $this->assertEquals('2026-06-21', $fields['date']['value']);
        $this->assertEquals('10:00', $fields['time']['value']);
        $this->assertEquals('2026-06-21 10:00', $fields['due_at']['value']);

        // 4. Execution
        $this->postJson("/api/actions/{$proposalId}/confirm")->assertStatus(200);
        $this->postJson("/api/actions/{$proposalId}/execute")->assertStatus(200);

        $task = Task::first();
        $this->assertNotNull($task->due_at);
        $this->assertEquals('2026-06-21 10:00:00', $task->due_at->format('Y-m-d H:i:s'));

        Carbon::setTestNow();
    }

    // --- not found / auth ---

    public function test_missing_proposal_returns_404(): void
    {
        $this->actingAsUser();

        $this->postJson('/api/actions/00000000-0000-0000-0000-000000000000/execute')
            ->assertStatus(404);
    }

    public function test_unauthenticated_execute_returns_401(): void
    {
        $this->postJson('/api/actions/some-id/execute')
            ->assertStatus(401);
    }
}
