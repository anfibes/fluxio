<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 7B — Context-Aware Temporal Refinements.
 *
 * The refinement service resolves a relative temporal-shift mutation against the
 * proposal's current time (via ProposalRuntimeContext) and applies a concrete
 * time replace. Deterministic, capability-gated, and never invents a time.
 * These tests exercise the full HTTP refine flow; they do not snapshot payloads.
 */
class ContextualTemporalShiftRefinementTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    private function readyScheduleCall(User $user, ?string $time = '10:00'): ActionProposal
    {
        $timeField = $time === null
            ? ['key' => 'time', 'label' => 'Time', 'value' => null, 'source' => 'missing', 'required' => true]
            : ['key' => 'time', 'label' => 'Time', 'value' => $time, 'source' => 'detected', 'required' => true];

        return ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'schedule_call',
            'status' => $time === null ? 'draft' : 'ready',
            'confidence' => 0.9,
            'source_text' => 'Schedule a call with Rossini',
            'entities' => array_filter(['lead' => 'Rossini', 'date' => '2026-05-31', 'time' => $time]),
            'missing' => $time === null ? [['key' => 'time', 'label' => 'Time', 'required' => true]] : [],
            'warnings' => [],
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossini', 'source' => 'detected', 'required' => true],
                ['key' => 'date', 'label' => 'Date', 'value' => '2026-05-31', 'source' => 'detected', 'required' => true],
                $timeField,
            ],
            'changes' => [
                ['type' => 'schedule', 'label' => 'Schedule call', 'module' => 'calendar', 'payload' => []],
            ],
            'needs_confirmation' => true,
        ]);
    }

    private function timeField(array $data): ?array
    {
        foreach ($data['editable_fields'] as $field) {
            if ($field['key'] === 'time') {
                return $field;
            }
        }

        return null;
    }

    // ── Happy path shifts ─────────────────────────────────────────────────────

    public function test_push_it_by_30_minutes_shifts_time_forward(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->readyScheduleCall($user, '10:00');

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Push it by 30 minutes']);

        $response->assertStatus(200)->assertJsonPath('data.status', 'ready');
        $this->assertSame('10:30', $this->timeField($response->json('data'))['value']);
    }

    public function test_move_it_one_hour_earlier_shifts_time_backward(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->readyScheduleCall($user, '10:00');

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Move it one hour earlier']);

        $response->assertStatus(200);
        $this->assertSame('09:00', $this->timeField($response->json('data'))['value']);
    }

    public function test_move_it_2_hours_later_shifts_two_hours(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->readyScheduleCall($user, '10:00');

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Move it 2 hours later']);

        $response->assertStatus(200);
        $this->assertSame('12:00', $this->timeField($response->json('data'))['value']);
    }

    public function test_shift_marks_computed_source_and_records_a_change(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->readyScheduleCall($user, '10:00');

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Push it by 30 minutes']);

        $field = $this->timeField($response->json('data'));
        $this->assertSame('computed', $field['source']);

        $changes = $response->json('data.last_refinement.changes');
        $timeChange = collect($changes)->firstWhere('field', 'time');
        $this->assertSame('10:00', $timeChange['from']);
        $this->assertSame('10:30', $timeChange['to']);
        // Phase 7C: the change keeps the shift intent, not a plain replace.
        $this->assertSame('shift_time', $timeChange['semantic_type']);
    }

    // ── Phase 7C: semantic mutation type surfaced in change metadata ──────────

    public function test_absolute_time_refinement_is_classified_replace_time(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->readyScheduleCall($user, '10:00');

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'At 14:30']);

        $timeChange = collect($response->json('data.last_refinement.changes'))->firstWhere('field', 'time');
        $this->assertSame('14:30', $timeChange['to']);
        $this->assertSame('replace_time', $timeChange['semantic_type']);
    }

    public function test_date_refinement_is_classified_replace_date(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->readyScheduleCall($user, '10:00');

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Friday instead']);

        $dateChange = collect($response->json('data.last_refinement.changes'))->firstWhere('field', 'date');
        $this->assertNotNull($dateChange);
        $this->assertSame('replace_date', $dateChange['semantic_type']);
    }

    // ── No current time: never invent one ───────────────────────────────────────

    public function test_shift_without_current_time_does_not_invent_a_time(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->readyScheduleCall($user, null);   // draft, time missing

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Push it by 30 minutes']);

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'draft')                       // status unchanged
            ->assertJsonPath('data.last_refinement.summary', 'No changes applied.');

        $data = $response->json('data');
        $this->assertNull($this->timeField($data)['value']);               // still no time
        $this->assertSame('missing', $this->timeField($data)['source']);
        $this->assertContains('time', array_column($data['missing'], 'key')); // time stays missing
        $this->assertContains(
            'Cannot shift the time because the proposal has no current time.',
            $data['warnings'],
        );
        // The specific warning replaces the generic "not recognized" one.
        $this->assertNotContains(
            'The refinement could not be applied. The proposal was left unchanged.',
            $data['warnings'],
        );
    }

    // ── Capability gating ───────────────────────────────────────────────────────

    public function test_shift_rejected_for_intent_without_time_replacement(): void
    {
        $user = $this->actingAsUser();

        // assign_lead allows only lead/assignee replacement — no time replace.
        $proposal = ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'assign_lead',
            'status' => 'ready',
            'confidence' => 0.9,
            'source_text' => 'Assign Rossini to Marco',
            'entities' => ['lead' => 'Rossini', 'assignee' => 'Marco'],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossini', 'source' => 'detected', 'required' => true],
                ['key' => 'assignee', 'label' => 'Assignee', 'value' => 'Marco', 'source' => 'detected', 'required' => true],
            ],
            'changes' => [['type' => 'assign', 'label' => 'Assign lead', 'module' => 'leads', 'payload' => []]],
            'needs_confirmation' => true,
        ]);

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Push it by 30 minutes']);

        $response->assertStatus(200)
            ->assertJsonPath('data.last_refinement.summary', 'No changes applied.');

        $data = $response->json('data');
        $this->assertContains(
            'One or more requested changes are not supported for this action type.',
            $data['warnings'],
        );
        // No time field was invented.
        $this->assertNull($this->timeField($data));
    }

    // ── Existing absolute refinement still works (regression) ────────────────────

    public function test_absolute_time_refinement_still_applies(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->readyScheduleCall($user, '10:00');

        $response = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'At 14:30']);

        $response->assertStatus(200);
        $this->assertSame('14:30', $this->timeField($response->json('data'))['value']);
    }
}
