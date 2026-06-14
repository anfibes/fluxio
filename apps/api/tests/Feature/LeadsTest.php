<?php

namespace Tests\Feature;

use App\Models\User;
use Fluxio\Leads\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LeadsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    // --- authentication ---

    public function test_unauthenticated_access_returns_401(): void
    {
        $this->getJson('/api/leads')->assertStatus(401);
        $this->postJson('/api/leads', [])->assertStatus(401);
        $this->getJson('/api/leads/1')->assertStatus(401);
        $this->patchJson('/api/leads/1', [])->assertStatus(401);
        $this->deleteJson('/api/leads/1')->assertStatus(401);
    }

    // --- index ---

    public function test_authenticated_user_can_list_leads(): void
    {
        $this->actingAsUser();
        Lead::factory()->count(3)->create();

        $response = $this->getJson('/api/leads');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Leads retrieved successfully.')
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_index_respects_per_page_parameter(): void
    {
        $this->actingAsUser();
        Lead::factory()->count(3)->create();

        $response = $this->getJson('/api/leads?per_page=2');

        $response->assertStatus(200)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);

        $this->assertCount(2, $response->json('data'));
    }

    // --- search ---

    public function test_index_supports_search_by_name(): void
    {
        $this->actingAsUser();
        Lead::factory()->create(['name' => 'Alice Wonder', 'company' => 'Foo Inc', 'email' => 'alice@foo.com']);
        Lead::factory()->create(['name' => 'Bob Builder', 'company' => 'Bar Ltd', 'email' => 'bob@bar.com']);

        $response = $this->getJson('/api/leads?search=Alice');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Alice Wonder');
    }

    public function test_index_supports_search_by_company(): void
    {
        $this->actingAsUser();
        Lead::factory()->create(['name' => 'Alice Wonder', 'company' => 'Acme Corp']);
        Lead::factory()->create(['name' => 'Bob Builder', 'company' => 'Other Ltd']);

        $response = $this->getJson('/api/leads?search=Acme');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.company', 'Acme Corp');
    }

    public function test_index_supports_search_by_email(): void
    {
        $this->actingAsUser();
        Lead::factory()->create(['name' => 'Alice Wonder', 'email' => 'alice@acme.com']);
        Lead::factory()->create(['name' => 'Bob Builder', 'email' => 'bob@other.com']);

        $response = $this->getJson('/api/leads?search=acme');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.email', 'alice@acme.com');
    }

    public function test_index_search_excludes_unrelated_leads(): void
    {
        $this->actingAsUser();
        Lead::factory()->create(['name' => 'Alice Wonder', 'company' => 'Acme', 'email' => 'alice@acme.com']);
        Lead::factory()->create(['name' => 'Bob Builder', 'company' => 'Other Ltd', 'email' => 'bob@other.com']);
        Lead::factory()->create(['name' => 'Carol Smith', 'company' => 'Third Co', 'email' => 'carol@third.com']);

        $response = $this->getJson('/api/leads?search=Alice');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        $names = collect($response->json('data'))->pluck('name');
        $this->assertContains('Alice Wonder', $names);
        $this->assertNotContains('Bob Builder', $names);
        $this->assertNotContains('Carol Smith', $names);
    }

    // --- status filter ---

    public function test_index_supports_status_filter(): void
    {
        $this->actingAsUser();
        Lead::factory()->create(['status' => 'new']);
        Lead::factory()->create(['status' => 'won']);
        Lead::factory()->create(['status' => 'won']);

        $response = $this->getJson('/api/leads?status=won');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    // --- store ---

    public function test_authenticated_user_can_create_a_lead(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/leads', [
            'name' => 'Jane Doe',
            'company' => 'Acme Corp',
            'email' => 'jane@acme.com',
            'phone' => '555-1234',
            'status' => 'new',
            'notes' => 'Interested in product.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Lead created successfully.')
            ->assertJsonPath('data.name', 'Jane Doe')
            ->assertJsonPath('data.email', 'jane@acme.com')
            ->assertJsonPath('data.status', 'new');

        $this->assertDatabaseHas('leads', ['email' => 'jane@acme.com']);
    }

    public function test_create_defaults_status_to_new_when_omitted(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/leads', ['name' => 'Default Status Lead']);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'new');

        $this->assertDatabaseHas('leads', ['name' => 'Default Status Lead', 'status' => 'new']);
    }

    public function test_create_returns_422_on_missing_name(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/leads', ['company' => 'Acme Corp']);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_create_returns_422_on_invalid_status(): void
    {
        $this->actingAsUser();

        $response = $this->postJson('/api/leads', [
            'name' => 'Jane Doe',
            'status' => 'invalid-status',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['status']]);
    }

    // --- show ---

    public function test_authenticated_user_can_view_a_lead(): void
    {
        $this->actingAsUser();
        $lead = Lead::factory()->create([
            'name' => 'Jane Doe',
            'company' => 'Acme Corp',
            'email' => 'jane@acme.com',
            'phone' => '555-1234',
            'status' => 'contacted',
            'notes' => 'Some notes.',
        ]);

        $response = $this->getJson("/api/leads/{$lead->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Lead retrieved successfully.')
            ->assertJsonStructure([
                'data' => ['id', 'name', 'company', 'email', 'phone', 'status', 'notes', 'created_at', 'updated_at'],
            ])
            ->assertJsonPath('data.id', $lead->id)
            ->assertJsonPath('data.name', 'Jane Doe')
            ->assertJsonPath('data.company', 'Acme Corp')
            ->assertJsonPath('data.email', 'jane@acme.com')
            ->assertJsonPath('data.phone', '555-1234')
            ->assertJsonPath('data.status', 'contacted')
            ->assertJsonPath('data.notes', 'Some notes.');

        $this->assertNotNull($response->json('data.created_at'));
        $this->assertNotNull($response->json('data.updated_at'));
    }

    public function test_show_returns_404_for_missing_lead(): void
    {
        $this->actingAsUser();

        $this->getJson('/api/leads/999')->assertStatus(404);
    }

    // --- update ---

    public function test_authenticated_user_can_update_a_lead(): void
    {
        $this->actingAsUser();
        $lead = Lead::factory()->create(['status' => 'new']);

        $response = $this->patchJson("/api/leads/{$lead->id}", [
            'status' => 'contacted',
            'notes' => 'Followed up via email.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Lead updated successfully.')
            ->assertJsonPath('data.status', 'contacted')
            ->assertJsonPath('data.notes', 'Followed up via email.');

        $this->assertDatabaseHas('leads', ['id' => $lead->id, 'status' => 'contacted']);
    }

    public function test_update_returns_422_on_invalid_status(): void
    {
        $this->actingAsUser();
        $lead = Lead::factory()->create();

        $response = $this->patchJson("/api/leads/{$lead->id}", [
            'status' => 'not-a-real-status',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['status']]);
    }

    public function test_update_returns_404_for_missing_lead(): void
    {
        $this->actingAsUser();

        $this->patchJson('/api/leads/999', ['status' => 'won'])->assertStatus(404);
    }

    // --- destroy ---

    public function test_authenticated_user_can_delete_a_lead(): void
    {
        $this->actingAsUser();
        $lead = Lead::factory()->create();

        $response = $this->deleteJson("/api/leads/{$lead->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Lead deleted successfully.');

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
    }

    public function test_delete_returns_404_for_missing_lead(): void
    {
        $this->actingAsUser();

        $this->deleteJson('/api/leads/999')->assertStatus(404);
    }

    // --- assigned_to shape ---

    public function test_unassigned_lead_has_null_assigned_to(): void
    {
        $this->actingAsUser();
        $lead = Lead::factory()->create();

        $response = $this->getJson("/api/leads/{$lead->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.assigned_to', null)
            ->assertJsonPath('data.assigned_at', null);
    }

    public function test_assigned_lead_exposes_id_and_name_in_assigned_to(): void
    {
        $assignee = User::factory()->create(['name' => 'Marco Rossi']);
        $this->actingAsUser();
        $lead = Lead::factory()->create([
            'assigned_to_user_id' => $assignee->id,
            'assigned_at'         => now(),
        ]);

        $response = $this->getJson("/api/leads/{$lead->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.assigned_to.id', $assignee->id)
            ->assertJsonPath('data.assigned_to.name', 'Marco Rossi');

        $this->assertNotNull($response->json('data.assigned_at'));
    }

    public function test_lead_list_includes_assigned_to_for_each_item(): void
    {
        $assignee = User::factory()->create(['name' => 'Sara']);
        $this->actingAsUser();
        Lead::factory()->create(['assigned_to_user_id' => $assignee->id, 'assigned_at' => now()]);
        Lead::factory()->create(); // unassigned

        $response = $this->getJson('/api/leads');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);

        $assigned   = collect($data)->firstWhere('assigned_to', '!==', null);
        $unassigned = collect($data)->firstWhere('assigned_to', null);

        $this->assertNotNull($assigned);
        $this->assertEquals($assignee->id, $assigned['assigned_to']['id']);
        $this->assertNotNull($unassigned);
    }
}
