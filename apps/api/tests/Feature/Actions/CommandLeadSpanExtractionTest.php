<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Fluxio\Actions\Interpreters\RuleBasedCommandInterpreter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 9D.1 — command interpretation preserves the full user-facing lead
 * reference span (e.g. "Mario Rossi", "Rossi SRL"), so resolver-backed entities
 * receive the richest deterministic query and can exact-match instead of surfacing
 * a spurious ambiguity. Matching/identity/ambiguity stay owned by the resolver.
 */
class CommandLeadSpanExtractionTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function interpret(string $text): array
    {
        $response = $this->postJson('/api/actions/interpret', ['text' => $text]);
        $response->assertStatus(200);

        return $response->json('data');
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $ambiguities
     */
    private function assertNoBlockingAmbiguity(?array $ambiguities): void
    {
        foreach ($ambiguities ?? [] as $ambiguity) {
            $this->assertFalse(
                $ambiguity['blocking'] ?? false,
                'Expected no blocking ambiguity (full lead span should have exact-matched).',
            );
        }
    }

    // 1. create_task preserves a full person lead span -------------------------

    public function test_create_task_preserves_full_person_lead_span(): void
    {
        $this->actingAsUser();

        $data = $this->interpret('Create a high priority follow-up task for Mario Rossi tomorrow at 10');

        $this->assertEquals('create_task', $data['intent']);

        // No spurious ambiguity — the resolver exact-matched the person.
        $this->assertNoBlockingAmbiguity($data['ambiguities']);

        // Lead resolved consistently in entities and editable fields.
        $this->assertEquals('Mario Rossi', $data['entities']['lead'] ?? null);
        $fields = collect($data['editable_fields'])->keyBy('key');
        $this->assertEquals('Mario Rossi', $fields['lead']['value'] ?? null);

        // No required field missing → ready.
        $this->assertEquals('ready', $data['status']);

        // Title remains reasonable, and date/time were still extracted (create_task
        // keeps temporal values in entities rather than as editable fields).
        $this->assertNotEmpty($fields['title']['value'] ?? null);
        $this->assertEquals(now()->addDay()->toDateString(), $data['entities']['date'] ?? null);
        $this->assertEquals('10:00', $data['entities']['time'] ?? null);
    }

    // 2. create_task preserves company / studio lead spans ---------------------

    public function test_create_task_preserves_company_lead_span(): void
    {
        $this->actingAsUser();

        $data = $this->interpret('Create a follow-up task for Rossi SRL tomorrow');

        $this->assertNoBlockingAmbiguity($data['ambiguities']);
        $this->assertEquals('Rossi SRL', $data['entities']['lead'] ?? null);
        $this->assertEquals('ready', $data['status']);
    }

    public function test_create_task_preserves_studio_lead_span(): void
    {
        $this->actingAsUser();

        $data = $this->interpret('Create a high priority follow-up task for Studio Rossi tomorrow');

        $this->assertNoBlockingAmbiguity($data['ambiguities']);
        $this->assertEquals('Studio Rossi', $data['entities']['lead'] ?? null);
        $this->assertEquals('ready', $data['status']);
    }

    // 3. schedule_call / schedule_meeting preserve full lead spans -------------

    public function test_schedule_call_preserves_full_person_lead_span(): void
    {
        $this->actingAsUser();

        $data = $this->interpret('Schedule a call with Mario Rossi tomorrow at 10');

        $this->assertEquals('schedule_call', $data['intent']);
        $this->assertNoBlockingAmbiguity($data['ambiguities']);
        $this->assertEquals('Mario Rossi', $data['entities']['lead'] ?? null);
        $this->assertEquals(now()->addDay()->toDateString(), $data['entities']['date'] ?? null);
        $this->assertEquals('10:00', $data['entities']['time'] ?? null);
        $this->assertEquals('ready', $data['status']);
    }

    public function test_schedule_meeting_preserves_company_lead_span(): void
    {
        $this->actingAsUser();

        $data = $this->interpret('Schedule a meeting with Rossi SRL next Friday at 3pm');

        $this->assertEquals('schedule_meeting', $data['intent']);
        $this->assertNoBlockingAmbiguity($data['ambiguities']);
        $this->assertEquals('Rossi SRL', $data['entities']['lead'] ?? null);
        $this->assertNotNull($data['entities']['date'] ?? null);
        $this->assertEquals('15:00', $data['entities']['time'] ?? null);
    }

    // 4. ambiguous shorter query still remains ambiguous -----------------------

    public function test_short_lead_query_remains_ambiguous(): void
    {
        $this->actingAsUser();

        $data = $this->interpret('Schedule a call with Rossi tomorrow at 10');

        // The span is genuinely just "Rossi" — the resolver must still surface the
        // blocking ambiguity (this proves the fix did not remove valid behavior).
        $this->assertNotEmpty($data['ambiguities']);
        $this->assertEquals('lead', $data['ambiguities'][0]['key']);
        $this->assertEquals('Rossi', $data['ambiguities'][0]['query']);
        $this->assertTrue($data['ambiguities'][0]['blocking']);
        $this->assertNull($data['ambiguities'][0]['selected_candidate_id']);
        $this->assertEquals('draft', $data['status']);
    }

    // 5. interpreter preserves the span but emits no identity ------------------

    public function test_interpreter_preserves_span_and_emits_no_identity(): void
    {
        /** @var RuleBasedCommandInterpreter $interpreter */
        $interpreter = $this->app->make(RuleBasedCommandInterpreter::class);

        $command = $interpreter->interpret('Create a high priority follow-up task for Mario Rossi tomorrow at 10');

        // The full span reaches the resolver as lead_query …
        $this->assertEquals('Mario Rossi', $command->entities['lead_query'] ?? null);

        // … and the interpreter never resolves identity or emits ids.
        $this->assertArrayNotHasKey('lead', $command->entities);
        $this->assertArrayNotHasKey('lead_id', $command->entities);
        $this->assertArrayNotHasKey('selected_candidate_id', $command->entities);
    }
}
