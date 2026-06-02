<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Carbon\Carbon;
use Fluxio\Actions\DTO\NormalizedCommand;
use Fluxio\Actions\Exceptions\InvalidNormalizedCommandException;
use Fluxio\Actions\Interpretation\Contracts\InterpretationProviderInterface;
use Fluxio\Actions\Interpretation\DTO\InterpretationContext;
use Fluxio\Actions\Interpretation\Providers\DeterministicInterpretationProvider;
use Fluxio\Actions\Interpretation\Providers\FakeLlmInterpretationProvider;
use Fluxio\Actions\Validation\NormalizedCommandValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NormalizedCommandValidationIntegrationTest extends TestCase
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

    // ── DeterministicInterpretationProvider output validates successfully ─────

    public function test_deterministic_provider_output_passes_validation_for_schedule_meeting(): void
    {
        $provider = $this->app->make(DeterministicInterpretationProvider::class);
        $validator = $this->app->make(NormalizedCommandValidator::class);

        $command = $provider->interpret('Schedule a meeting with Rossini tomorrow at 4pm', new InterpretationContext);
        $result = $validator->validate($command);

        $this->assertTrue($result->valid, implode('; ', $result->errors));
    }

    public function test_deterministic_provider_output_passes_validation_for_rossi_ambiguous(): void
    {
        $provider = $this->app->make(DeterministicInterpretationProvider::class);
        $validator = $this->app->make(NormalizedCommandValidator::class);

        $command = $provider->interpret('Schedule a meeting with Rossi tomorrow morning', new InterpretationContext);
        $result = $validator->validate($command);

        $this->assertTrue($result->valid, implode('; ', $result->errors));
    }

    public function test_deterministic_provider_output_passes_validation_for_create_task(): void
    {
        $provider = $this->app->make(DeterministicInterpretationProvider::class);
        $validator = $this->app->make(NormalizedCommandValidator::class);

        $command = $provider->interpret('Create a task for Rossini tomorrow at 10am', new InterpretationContext);
        $result = $validator->validate($command);

        $this->assertTrue($result->valid, implode('; ', $result->errors));
    }

    public function test_deterministic_provider_output_passes_for_unknown_intent(): void
    {
        $provider = $this->app->make(DeterministicInterpretationProvider::class);
        $validator = $this->app->make(NormalizedCommandValidator::class);

        $command = $provider->interpret('Show me the dashboard', new InterpretationContext);
        $result = $validator->validate($command);

        $this->assertTrue($result->valid, implode('; ', $result->errors));
    }

    // ── FakeLlmInterpretationProvider: valid output passes ────────────────────

    public function test_fake_provider_valid_output_passes_validation(): void
    {
        $validator = $this->app->make(NormalizedCommandValidator::class);
        $command = new NormalizedCommand(
            intent: 'schedule_meeting',
            confidence: 0.95,
            sourceText: 'Book me a meeting',
            locale: 'en',
            entities: ['lead_query' => 'Rossini'],
        );

        $result = $validator->validate($command);

        $this->assertTrue($result->valid, implode('; ', $result->errors));
    }

    // ── FakeLlmInterpretationProvider: invalid output is rejected by adapter ──

    public function test_fake_provider_invalid_intent_is_rejected_by_adapter(): void
    {
        $fake = new FakeLlmInterpretationProvider;
        $fake->addResponse('do something', new NormalizedCommand(
            intent: 'hallucinated_action',
            confidence: 0.9,
            sourceText: 'do something',
            locale: 'en',
        ));

        $this->app->bind(InterpretationProviderInterface::class, fn () => $fake);

        $this->expectException(InvalidNormalizedCommandException::class);

        $adapter = $this->app->make(\Fluxio\Actions\Contracts\CommandInterpreterInterface::class);
        $adapter->interpret('do something');
    }

    public function test_fake_provider_invalid_confidence_is_rejected_by_adapter(): void
    {
        $fake = new FakeLlmInterpretationProvider;
        $fake->addResponse('book a meeting', new NormalizedCommand(
            intent: 'schedule_meeting',
            confidence: 1.5,
            sourceText: 'book a meeting',
            locale: 'en',
        ));

        $this->app->bind(InterpretationProviderInterface::class, fn () => $fake);

        $this->expectException(InvalidNormalizedCommandException::class);

        $adapter = $this->app->make(\Fluxio\Actions\Contracts\CommandInterpreterInterface::class);
        $adapter->interpret('book a meeting');
    }

    public function test_fake_provider_entity_null_value_is_rejected_by_adapter(): void
    {
        $fake = new FakeLlmInterpretationProvider;
        $fake->addResponse('create a task', new NormalizedCommand(
            intent: 'create_task',
            confidence: 0.9,
            sourceText: 'create a task',
            locale: 'en',
            entities: ['lead' => null],
        ));

        $this->app->bind(InterpretationProviderInterface::class, fn () => $fake);

        $this->expectException(InvalidNormalizedCommandException::class);

        $adapter = $this->app->make(\Fluxio\Actions\Contracts\CommandInterpreterInterface::class);
        $adapter->interpret('create a task');
    }

    // ── Value-shape validation: malformed date/time fails before proposal building ──

    public function test_fake_provider_malformed_date_is_rejected_by_adapter(): void
    {
        $fake = new FakeLlmInterpretationProvider;
        $fake->addResponse('schedule a meeting', new NormalizedCommand(
            intent: 'schedule_meeting',
            confidence: 0.9,
            sourceText: 'schedule a meeting',
            locale: 'en',
            entities: ['lead_query' => 'Rossini', 'date' => 'next week', 'time' => '10:00'],
        ));

        $this->app->bind(InterpretationProviderInterface::class, fn () => $fake);

        $this->expectException(InvalidNormalizedCommandException::class);

        $adapter = $this->app->make(\Fluxio\Actions\Contracts\CommandInterpreterInterface::class);
        $adapter->interpret('schedule a meeting');
    }

    public function test_fake_provider_malformed_time_is_rejected_by_adapter(): void
    {
        $fake = new FakeLlmInterpretationProvider;
        $fake->addResponse('schedule a meeting', new NormalizedCommand(
            intent: 'schedule_meeting',
            confidence: 0.9,
            sourceText: 'schedule a meeting',
            locale: 'en',
            entities: ['date' => '2026-06-02', 'time' => 'morning-ish'],
        ));

        $this->app->bind(InterpretationProviderInterface::class, fn () => $fake);

        $this->expectException(InvalidNormalizedCommandException::class);

        $adapter = $this->app->make(\Fluxio\Actions\Contracts\CommandInterpreterInterface::class);
        $adapter->interpret('schedule a meeting');
    }

    public function test_fake_provider_raw_date_expression_is_accepted_by_adapter(): void
    {
        // date_expression is an interpretation-side raw expression — allowed, unconstrained.
        $fake = new FakeLlmInterpretationProvider;
        $fake->addResponse('schedule a meeting', new NormalizedCommand(
            intent: 'schedule_meeting',
            confidence: 0.9,
            sourceText: 'schedule a meeting',
            locale: 'en',
            entities: ['date_expression' => 'tomorrow', 'time_expression' => 'morning-ish'],
        ));

        $this->app->bind(InterpretationProviderInterface::class, fn () => $fake);

        $adapter = $this->app->make(\Fluxio\Actions\Contracts\CommandInterpreterInterface::class);
        $command = $adapter->interpret('schedule a meeting');

        $this->assertSame('tomorrow', $command->entities['date_expression']);
        $this->assertSame('morning-ish', $command->entities['time_expression']);
    }

    public function test_malformed_date_provider_output_returns_422_and_builds_no_proposal(): void
    {
        $this->actingAsUser();

        // Expected fail-closed path: invalid provider output is reported as a 422.
        // Fake reporting for ONLY this exception so its expected message does not pollute
        // laravel.log; rendering still delegates to the real handler (422 preserved).
        Exceptions::fake([InvalidNormalizedCommandException::class]);

        $fake = new FakeLlmInterpretationProvider;
        $fake->addResponse('book it', new NormalizedCommand(
            intent: 'schedule_meeting',
            confidence: 0.9,
            sourceText: 'book it',
            locale: 'en',
            entities: ['date' => 'next week'],
        ));

        $this->app->bind(InterpretationProviderInterface::class, fn () => $fake);

        $this->postJson('/api/actions/interpret', ['text' => 'book it'])
            ->assertStatus(422);

        // Malformed value was rejected at the interpretation boundary — nothing built.
        $this->assertDatabaseCount('action_proposals', 0);

        // Suppressed from the log, not swallowed — fail-closed validation still fired.
        Exceptions::assertReported(InvalidNormalizedCommandException::class);
    }

    // ── Phase 9F.0 provider sandbox: surface lock enforced at the adapter ─────

    public function test_fake_provider_participant_query_is_rejected_by_sandbox(): void
    {
        // participant_query IS a declared entityType for schedule_meeting, so the
        // NormalizedCommandValidator accepts it — the SANDBOX is what rejects it,
        // because the runtime resolves only lead_query today.
        $fake = new FakeLlmInterpretationProvider;
        $fake->addResponse('book it', new NormalizedCommand(
            intent: 'schedule_meeting',
            confidence: 0.9,
            sourceText: 'book it',
            locale: 'en',
            entities: ['lead_query' => 'Rossini', 'participant_query' => 'Marco'],
        ));

        $this->app->bind(InterpretationProviderInterface::class, fn () => $fake);

        // It passes the structural validator on its own …
        $validator = $this->app->make(NormalizedCommandValidator::class);
        $command = $fake->interpret('book it', new InterpretationContext);
        $this->assertTrue($validator->validate($command)->valid);

        // … but the adapter's sandbox rejects it.
        $this->expectException(InvalidNormalizedCommandException::class);
        $this->app->make(\Fluxio\Actions\Contracts\CommandInterpreterInterface::class)->interpret('book it');
    }

    public function test_fake_provider_identity_surface_is_rejected_even_for_unknown_intent(): void
    {
        // unknown skips key-compatibility in the validator; the sandbox still rejects
        // a selected_candidate_id surface — proving the gap is closed.
        $fake = new FakeLlmInterpretationProvider;
        $fake->addResponse('do it', new NormalizedCommand(
            intent: 'unknown',
            confidence: 0.3,
            sourceText: 'do it',
            locale: 'en',
            entities: ['selected_candidate_id' => '7'],
        ));

        $this->app->bind(InterpretationProviderInterface::class, fn () => $fake);

        $this->expectException(InvalidNormalizedCommandException::class);
        $this->app->make(\Fluxio\Actions\Contracts\CommandInterpreterInterface::class)->interpret('do it');
    }

    public function test_deterministic_provider_output_is_sandbox_legal(): void
    {
        // Behavior-preserving: every deterministic command passes the sandbox.
        $provider = $this->app->make(DeterministicInterpretationProvider::class);
        $sandbox = new \Fluxio\Actions\Interpretation\ProviderSandboxContract;

        foreach ([
            'Create a high priority follow-up task for Mario Rossi tomorrow at 10',
            'Schedule a meeting with Rossi SRL next Friday at 3pm',
            'Schedule a call with Rossi tomorrow at 10',
            'Assign Mario Rossi to Marco',
            'Show me the dashboard',
        ] as $text) {
            $command = $provider->interpret($text, new InterpretationContext);
            $this->assertSame([], $sandbox->violations($command), "sandbox violation for: {$text}");
        }
    }

    // ── Feature regression tests: endpoint behavior unchanged ─────────────────

    public function test_schedule_meeting_with_rossini_endpoint_unchanged(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', [
            'text' => 'Schedule a meeting with Rossini tomorrow at 4pm',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'schedule_meeting')
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.ambiguities', []);

        $fields = collect($response->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Rossini', $fields['lead']['value']);
        $this->assertEquals(Carbon::now()->addDay()->toDateString(), $fields['date']['value']);
        $this->assertEquals('16:00', $fields['time']['value']);
    }

    public function test_rossi_ambiguity_endpoint_unchanged(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Schedule a meeting with Rossi tomorrow morning']);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'schedule_meeting')
            ->assertJsonPath('data.status', 'draft');

        $ambiguities = $response->json('data.ambiguities');
        $this->assertNotEmpty($ambiguities);
        $this->assertEquals('lead', $ambiguities[0]['key']);
        $this->assertTrue($ambiguities[0]['blocking']);
    }

    public function test_create_task_endpoint_unchanged(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Create a task']);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'create_task')
            ->assertJsonPath('data.status', 'ready');
    }

    public function test_the_second_one_refinement_unchanged(): void
    {
        $this->actingAsUser();

        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $r1->json('data.id');

        $r2 = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'The second one']);

        $r2->assertStatus(200)
            ->assertJsonPath('data.ambiguities.0.selected_candidate_id', 1);
    }

    public function test_full_proposal_lifecycle_unchanged(): void
    {
        $this->actingAsUser();

        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Schedule a meeting with Rossini tomorrow at 4pm']);
        $proposalId = $r1->json('data.id');
        $this->assertEquals('ready', $r1->json('data.status'));

        $this->postJson("/api/actions/{$proposalId}/confirm")->assertJsonPath('data.status', 'confirmed');
        $this->postJson("/api/actions/{$proposalId}/execute")->assertJsonPath('data.status', 'executed');
    }

    public function test_invalid_provider_output_returns_422_not_500(): void
    {
        // Expected fail-closed path: an unregistered intent is reported as a 422.
        // Fake reporting for ONLY this exception so its expected message does not pollute
        // laravel.log; rendering still delegates to the real handler (422 preserved).
        Exceptions::fake([InvalidNormalizedCommandException::class]);

        $fake = new FakeLlmInterpretationProvider;
        $fake->addResponse('do something bad', new NormalizedCommand(
            intent: 'hallucinated_action',
            confidence: 0.9,
            sourceText: 'do something bad',
            locale: 'en',
        ));

        $this->app->bind(InterpretationProviderInterface::class, fn () => $fake);

        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'do something bad']);

        $response->assertStatus(422);
        $response->assertJsonPath('success', false);
        $this->assertStringNotContainsString('hallucinated_action', $response->content());

        // Suppressed from the log, not swallowed — fail-closed validation still fired.
        Exceptions::assertReported(InvalidNormalizedCommandException::class);
    }
}
