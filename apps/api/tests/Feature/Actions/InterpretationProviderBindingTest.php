<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Carbon\Carbon;
use Fluxio\Actions\Contracts\CommandInterpreterInterface;
use Fluxio\Actions\Interpretation\Contracts\InterpretationProviderInterface;
use Fluxio\Actions\Interpretation\InterpretationProviderAdapter;
use Fluxio\Actions\Interpretation\Providers\DeterministicInterpretationProvider;
use Fluxio\Actions\Interpretation\Providers\FakeLlmInterpretationProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SeedsDemoLeads;
use Tests\TestCase;

class InterpretationProviderBindingTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoLeads;

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

    // ── Container bindings ───────────────────────────────────────────────────

    public function test_command_interpreter_interface_resolves_to_adapter(): void
    {
        $instance = $this->app->make(CommandInterpreterInterface::class);

        $this->assertInstanceOf(InterpretationProviderAdapter::class, $instance);
    }

    public function test_interpretation_provider_interface_resolves_to_deterministic_provider(): void
    {
        $instance = $this->app->make(InterpretationProviderInterface::class);

        $this->assertInstanceOf(DeterministicInterpretationProvider::class, $instance);
    }

    public function test_fake_llm_provider_is_not_the_default(): void
    {
        $instance = $this->app->make(InterpretationProviderInterface::class);

        $this->assertNotInstanceOf(FakeLlmInterpretationProvider::class, $instance);
    }

    // ── End-to-end: existing behavior unchanged ──────────────────────────────

    public function test_ambiguity_flow_unchanged_after_provider_swap(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);

        $response->assertStatus(200)
            ->assertJsonPath('data.intent', 'schedule_call')
            ->assertJsonPath('data.status', 'draft');

        $ambiguities = $response->json('data.ambiguities');
        $this->assertCount(1, $ambiguities);
        $this->assertEquals('lead', $ambiguities[0]['key']);
        $this->assertTrue($ambiguities[0]['blocking']);
        $this->assertCount(3, $ambiguities[0]['candidates']);
    }

    public function test_rossini_auto_resolve_unchanged(): void
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

    public function test_the_second_one_refinement_unchanged(): void
    {
        $this->actingAsUser();

        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Call Rossi']);
        $proposalId = $r1->json('data.id');

        $r2 = $this->postJson("/api/actions/{$proposalId}/refine", ['text' => 'The second one']);

        $r2->assertStatus(200)
            ->assertJsonPath('data.id', $proposalId)
            ->assertJsonPath('data.ambiguities.0.selected_candidate_id', 1);

        $fields = collect($r2->json('data.editable_fields'))->keyBy('key');
        $this->assertEquals('Mario Rossi', $fields['lead']['value']);
    }

    public function test_full_proposal_lifecycle_unchanged(): void
    {
        $this->actingAsUser();

        // Interpret
        $r1 = $this->postJson('/api/actions/interpret', ['text' => 'Schedule a meeting with Rossini tomorrow at 4pm']);
        $proposalId = $r1->json('data.id');
        $this->assertEquals('ready', $r1->json('data.status'));

        // Confirm
        $r2 = $this->postJson("/api/actions/{$proposalId}/confirm");
        $r2->assertStatus(200)->assertJsonPath('data.status', 'confirmed');

        // Execute
        $r3 = $this->postJson("/api/actions/{$proposalId}/execute");
        $r3->assertStatus(200)->assertJsonPath('data.status', 'executed');
        $this->assertEquals($proposalId, $r3->json('data.id'));
    }

    // ── Provider swap: FakeLlmInterpretationProvider in action ───────────────

    public function test_command_interpreter_interface_can_be_swapped_to_fake_provider(): void
    {
        $fake = new FakeLlmInterpretationProvider;
        $fake->addResponse('Book me a meeting', new \Fluxio\Actions\DTO\NormalizedCommand(
            intent: 'schedule_meeting',
            confidence: 0.99,
            sourceText: 'Book me a meeting',
            locale: 'en',
        ));

        $this->app->bind(InterpretationProviderInterface::class, fn () => $fake);

        $adapter = $this->app->make(CommandInterpreterInterface::class);
        $command = $adapter->interpret('Book me a meeting');

        $this->assertEquals('schedule_meeting', $command->intent);
        $this->assertEquals(0.99, $command->confidence);
    }
}
