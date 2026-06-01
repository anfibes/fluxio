<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Carbon\Carbon;
use Fluxio\Actions\Models\ActionProposal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * After a scalar replace refinement the proposal must stay internally consistent:
 * editable_fields, entities, and any change payload that declares the field are
 * all updated together. This guards against the UI showing a refined value while
 * entities / changes.payload keep the stale one. Applies generally to scalar
 * replaces (date, time, …), not only temporal shifts.
 */
class ScalarRefinementConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
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
     * schedule_meeting mirrors entities into changes[0].payload at build time,
     * so it exercises all three consistency targets at once.
     */
    private function readyScheduleMeeting(User $user): ActionProposal
    {
        $entities = ['lead' => 'Rossi', 'date' => '2026-05-31', 'time' => '10:00'];

        return ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'schedule_meeting',
            'status' => 'ready',
            'confidence' => 0.9,
            'source_text' => 'Schedule a meeting with Rossi tomorrow at 10:00',
            'entities' => $entities,
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossi', 'source' => 'detected', 'required' => true],
                ['key' => 'date', 'label' => 'Date', 'value' => '2026-05-31', 'source' => 'detected', 'required' => true],
                ['key' => 'time', 'label' => 'Time', 'value' => '10:00', 'source' => 'detected', 'required' => true],
            ],
            'changes' => [
                ['type' => 'schedule', 'label' => 'Schedule meeting', 'module' => 'calendar', 'payload' => $entities],
            ],
            'needs_confirmation' => true,
        ]);
    }

    private function fieldValue(array $data, string $key): mixed
    {
        foreach ($data['editable_fields'] as $field) {
            if ($field['key'] === $key) {
                return $field['value'];
            }
        }

        return null;
    }

    private function assertConsistent(array $data, string $field, mixed $expected): void
    {
        $this->assertSame($expected, $this->fieldValue($data, $field), "editable_fields[$field] mismatch");
        $this->assertSame($expected, $data['entities'][$field] ?? null, "entities[$field] mismatch");
        $this->assertSame($expected, $data['changes'][0]['payload'][$field] ?? null, "changes[0].payload[$field] mismatch");
    }

    public function test_contextual_temporal_shift_syncs_all_three(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->readyScheduleMeeting($user);

        $data = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Push it by 30 minutes'])
            ->assertStatus(200)
            ->json('data');

        $this->assertConsistent($data, 'time', '10:30');
        // Unrelated fields stay aligned and untouched.
        $this->assertConsistent($data, 'date', '2026-05-31');
        $this->assertConsistent($data, 'lead', 'Rossi');
    }

    public function test_absolute_time_refinement_syncs_all_three(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->readyScheduleMeeting($user);

        $data = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'At 14:30'])
            ->assertStatus(200)
            ->json('data');

        $this->assertConsistent($data, 'time', '14:30');
    }

    public function test_date_refinement_syncs_all_three(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->readyScheduleMeeting($user);

        $expectedDate = Carbon::parse('next friday')->toDateString();

        $data = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Friday instead'])
            ->assertStatus(200)
            ->json('data');

        $this->assertConsistent($data, 'date', $expectedDate);
        // Time is unchanged and still consistent.
        $this->assertConsistent($data, 'time', '10:00');
    }

    public function test_move_one_hour_earlier_syncs_all_three(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->readyScheduleMeeting($user);

        $data = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Move it one hour earlier'])
            ->assertStatus(200)
            ->json('data');

        $this->assertConsistent($data, 'time', '09:00');
    }

    /**
     * schedule_call payload is empty by convention; the fix must update entities
     * and editable_fields without inventing a payload key that was never there.
     */
    public function test_scalar_replace_does_not_invent_change_payload_keys(): void
    {
        $user = $this->actingAsUser();

        $proposal = ActionProposal::create([
            'user_id' => $user->id,
            'intent' => 'schedule_call',
            'status' => 'ready',
            'confidence' => 0.9,
            'source_text' => 'Schedule a call with Rossini tomorrow at 10:00',
            'entities' => ['lead' => 'Rossini', 'date' => '2026-05-31', 'time' => '10:00'],
            'missing' => [],
            'warnings' => [],
            'editable_fields' => [
                ['key' => 'lead', 'label' => 'Lead', 'value' => 'Rossini', 'source' => 'detected', 'required' => true],
                ['key' => 'date', 'label' => 'Date', 'value' => '2026-05-31', 'source' => 'detected', 'required' => true],
                ['key' => 'time', 'label' => 'Time', 'value' => '10:00', 'source' => 'detected', 'required' => true],
            ],
            'changes' => [
                ['type' => 'schedule', 'label' => 'Schedule call', 'module' => 'calendar', 'payload' => []],
            ],
            'needs_confirmation' => true,
        ]);

        $data = $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Push it by 30 minutes'])
            ->assertStatus(200)
            ->json('data');

        // editable_fields + entities updated …
        $this->assertSame('10:30', $this->fieldValue($data, 'time'));
        $this->assertSame('10:30', $data['entities']['time']);
        // … but the empty payload is not given a fabricated 'time' key.
        $this->assertSame([], $data['changes'][0]['payload']);
    }

    public function test_execution_uses_refined_time_after_shift(): void
    {
        $user = $this->actingAsUser();
        $proposal = $this->readyScheduleMeeting($user);

        $this->postJson("/api/actions/{$proposal->id}/refine", ['text' => 'Push it by 30 minutes'])
            ->assertStatus(200);

        $this->postJson("/api/actions/{$proposal->id}/confirm")->assertStatus(200);

        $result = $this->postJson("/api/actions/{$proposal->id}/execute")
            ->assertStatus(200)
            ->json('data.execution_result');

        $this->assertSame('10:30', $result['details']['time']);
    }
}
