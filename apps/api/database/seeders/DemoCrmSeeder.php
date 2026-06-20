<?php

namespace Database\Seeders;

use App\Models\User;
use Fluxio\Leads\Models\Lead;
use Fluxio\Tasks\Models\Task;
use Illuminate\Database\Seeder;

class DemoCrmSeeder extends Seeder
{
    /**
     * Seed a minimal CRM dataset for manual proposal-workflow testing.
     *
     * Creates 2 users, 6 leads (exercising new/contacted/qualified states and
     * the canonical ambiguity set), and 2 tasks linked to real leads so the
     * full lifecycle (create_task, assign_lead, update_task_status,
     * update_lead_status) can be exercised against real database rows.
     *
     * No IDs are hardcoded — every entity is created through Eloquent and
     * referenced by variable.
     */
    public function run(): void
    {
        // ── Users ──────────────────────────────────────────────────
        $admin = User::factory()->create([
            'name'  => 'Admin User',
            'email' => 'admin@fluxio.test',
        ]);

        $sara = User::factory()->create([
            'name'  => 'Sara Bianchi',
            'email' => 'sara@fluxio.test',
        ]);

        // ── Leads — canonical ambiguity set + extras ──────────────
        // Three "Rossi" variants (exercises ambiguity resolution for lead_query)
        // plus two standalone leads for assignment and status-update testing.

        $mario = Lead::create([
            'name'    => 'Mario Rossi',
            'company' => null,
            'email'   => 'mario.rossi@example.com',
            'status'  => 'contacted',
        ]);

        Lead::create([
            'name'    => 'Rossini',
            'company' => 'Rossini',
            'email'   => 'info@rossini.example.com',
            'status'  => 'new',
        ]);

        Lead::create([
            'name'    => 'Rossi SRL',
            'company' => 'Rossi SRL',
            'email'   => 'info@rossisrl.example.com',
            'status'  => 'new',
        ]);

        Lead::create([
            'name'    => 'Studio Rossi',
            'company' => 'Studio Rossi',
            'email'   => 'info@studiorossi.example.com',
            'status'  => 'new',
        ]);

        $sarah = Lead::create([
            'name'    => 'Sarah Chen',
            'company' => 'TechStart Inc.',
            'email'   => 'sarah@techstart.example.com',
            'status'  => 'qualified',
        ]);

        $marco = Lead::create([
            'name'    => 'Marco Bianchi',
            'company' => 'Acme Corp',
            'email'   => 'marco@acme.example.com',
            'status'  => 'contacted',
        ]);

        // ── Tasks — linked to real leads ──────────────────────────
        Task::create([
            'title'       => 'Follow-up call with Acme Corp',
            'description' => 'Discuss proposal details and next steps.',
            'status'      => 'pending',
            'priority'    => 'high',
            'lead_id'     => $marco->id,
        ]);

        Task::create([
            'title'       => 'Product demo with TechStart',
            'description' => 'Walk through the platform for the TechStart team.',
            'status'      => 'pending',
            'priority'    => 'normal',
            'lead_id'     => $sarah->id,
        ]);

        // ── One lead pre-assigned (exercises the already-assigned path) ──
        $mario->update([
            'assigned_to_user_id' => $sara->id,
            'assigned_at'         => now(),
        ]);
    }
}
