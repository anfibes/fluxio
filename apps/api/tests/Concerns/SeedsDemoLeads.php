<?php

namespace Tests\Concerns;

use Fluxio\Leads\Models\Lead;

/**
 * Seeds the canonical demo leads as REAL database rows.
 *
 * These are the leads that used to live in the (now removed) InMemoryLeadRepository.
 * Many interpretation/ambiguity tests assert against their stable ids and ordering
 * (Mario Rossi = 1, Rossini = 5, Rossi SRL = 7, Studio Rossi = 12), so the ids are
 * pinned here. Because LeadEntityResolver is now DB-backed, a test that exercises
 * lead resolution must have these rows present — `use SeedsDemoLeads;` does that, and
 * Laravel's `setUpTraits()` runs the hook automatically after RefreshDatabase migrates.
 *
 * Type signal: a lead carrying a company value is a company-type contact, otherwise a
 * person — Mario Rossi (no company) is the lone person, matching the old fixtures.
 */
trait SeedsDemoLeads
{
    protected function setUpSeedsDemoLeads(): void
    {
        $this->seedDemoLeads();
    }

    protected function seedDemoLeads(): void
    {
        $rows = [
            ['id' => 1,  'name' => 'Mario Rossi',  'company' => null,           'status' => 'new'],
            ['id' => 5,  'name' => 'Rossini',      'company' => 'Rossini',      'status' => 'new'],
            ['id' => 7,  'name' => 'Rossi SRL',    'company' => 'Rossi SRL',    'status' => 'new'],
            ['id' => 12, 'name' => 'Studio Rossi', 'company' => 'Studio Rossi', 'status' => 'new'],
        ];

        foreach ($rows as $row) {
            $lead = Lead::factory()->make([
                'name' => $row['name'],
                'company' => $row['company'],
                'status' => $row['status'],
            ]);
            // Set the primary key directly so it is part of the INSERT (the assertions
            // depend on these exact ids); factory fill would skip the guarded key.
            $lead->id = $row['id'];
            $lead->save();
        }
    }
}
