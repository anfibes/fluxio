<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Carbon\Carbon;
use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 7C.1: after a date/time refinement the editable field explanation must
 * describe the new value, never the original command. These tests start from a
 * proposal whose time/date fields carry stale explanations and assert they are
 * refreshed (or coherent) after refinement. Explainability only — lifecycle,
 * entities/changes consistency, and semantic types are also asserted unchanged.
 */
class TemporalExplanationRefreshTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Saturday — "next friday" resolves to 2026-06-05.
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

    /**
     * Ready schedule_meeting whose time/date fields carry an initial explanation
     * referencing the original command (the stale state we must refresh).
     */
    private function proposalWithStaleExplanations(User $user): ActionProposal
    {
        $entities = ['lead' => 'Rossi', 'date' => '2026-05-31', 'time' => '10:00'];

        return ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'schedule_meeting',
            'status' => 'ready',
            'confidence' => 0.9,
            'source_text' => 'Schedule a meeting with Rossi tomorrow at 10',
            'entities' => $entities,
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossi', 'source' => 'detected', 'required' => true],
                ['key' => 'date', 'label' => 'Date', 'value' => '2026-05-31', 'source' => 'detected', 'required' => true,
                    'explanation' => ['source' => 'relative', 'expression' => 'tomorrow', 'confidence' => 0.9, 'message' => "Date resolved from 'tomorrow'."]],
                ['key' => 'time', 'label' => 'Time', 'value' => '10:00', 'source' => 'detected', 'required' => true,
                    'explanation' => ['source' => 'explicit', 'expression' => 'at 10', 'confidence' => 1.0, 'message' => "Time set from 'at 10' as 10:00."]],
            ],
            'changes' => [
                ['type' => 'schedule', 'label' => 'Schedule meeting', 'module' => 'calendar', 'payload' => $entities],
            ],
            'needs_confirmation' => true,
        ]);
    }

    private function field(array $data, string $key): ?array
    {
        foreach ($data['editable_fields'] as $f) {
            if ($f['key'] === $key) {
                return $f;
            }
        }

        return null;
    }

    private function semanticType(array $data, string $field): ?string
    {
        return collect($data['last_refinement']['changes'])->firstWhere('field', $field)['semantic_type'] ?? null;
    }

    public function test_direct_time_refinement_refreshes_explanation(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->proposalWithStaleExplanations($user);

        $data = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'At 11:00'])
            ->assertStatus(200)
            ->json('data');

        $time = $this->field($data, 'time');
        $this->assertSame('11:00', $time['value']);

        // Explanation is refreshed: no longer references the original expression/value.
        $this->assertSame('Time updated from refinement to 11:00.', $time['explanation']['message']);
        $this->assertStringNotContainsString('at 10', $time['explanation']['expression']);
        $this->assertStringNotContainsString('10:00', $time['explanation']['message']);
        $this->assertSame('explicit', $time['explanation']['source']);

        // Consistency + semantic type unchanged.
        $this->assertSame('11:00', $data['entities']['time']);
        $this->assertSame('11:00', $data['changes'][0]['payload']['time']);
        $this->assertSame('replace_time', $this->semanticType($data, 'time'));
        $this->assertSame('ready', $data['status']);
    }

    public function test_direct_date_refinement_refreshes_explanation(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->proposalWithStaleExplanations($user);

        $data = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Move it to Friday'])
            ->assertStatus(200)
            ->json('data');

        $expectedDate = Carbon::parse('next friday')->toDateString();
        $date = $this->field($data, 'date');

        $this->assertSame($expectedDate, $date['value']);
        $this->assertSame("Date updated from refinement to {$expectedDate}.", $date['explanation']['message']);
        $this->assertStringNotContainsString('tomorrow', $date['explanation']['expression']);
        $this->assertSame('weekday', $date['explanation']['source']);

        $this->assertSame($expectedDate, $data['entities']['date']);
        $this->assertSame($expectedDate, $data['changes'][0]['payload']['date']);
        $this->assertSame('replace_date', $this->semanticType($data, 'date'));
    }

    public function test_contextual_time_shift_describes_computed_shift(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->proposalWithStaleExplanations($user);

        $data = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Push it by 30 minutes'])
            ->assertStatus(200)
            ->json('data');

        $time = $this->field($data, 'time');
        $this->assertSame('10:30', $time['value']);

        // Explanation describes the computed contextual shift, not the original explicit time.
        $this->assertSame('computed', $time['explanation']['source']);
        $this->assertSame('Push it by 30 minutes', $time['explanation']['expression']);
        $this->assertSame('Time shifted later by 30 minutes to 10:30.', $time['explanation']['message']);
        $this->assertStringNotContainsString('at 10', $time['explanation']['expression']);

        $this->assertSame('10:30', $data['entities']['time']);
        $this->assertSame('10:30', $data['changes'][0]['payload']['time']);
        $this->assertSame('shift_time', $this->semanticType($data, 'time'));
        $this->assertSame('ready', $data['status']);
    }

    public function test_time_refinement_leaves_unrelated_date_explanation_untouched(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->proposalWithStaleExplanations($user);

        $data = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'At 11:00'])
            ->assertStatus(200)
            ->json('data');

        // Date was not refined, so its (still-accurate) explanation is preserved.
        $date = $this->field($data, 'date');
        $this->assertSame('2026-05-31', $date['value']);
        $this->assertSame("Date resolved from 'tomorrow'.", $date['explanation']['message']);
    }
}
