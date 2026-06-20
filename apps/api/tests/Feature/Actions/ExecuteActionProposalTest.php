<?php

namespace Tests\Feature\Actions;

use App\Models\User;
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
