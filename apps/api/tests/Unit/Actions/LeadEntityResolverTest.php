<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\EntityResolution\DTO\ResolutionContext;
use Fluxio\Actions\EntityResolution\Resolvers\LeadEntityResolver;
use Fluxio\Leads\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoLeads;
use Tests\TestCase;

/**
 * Verifies that the DB-backed LeadEntityResolver produces correct ResolutionResult
 * values for exact matches, ambiguous multi-matches, and unrecognised queries — now
 * scored against real Lead rows (the same rows the executors act on).
 */
class LeadEntityResolverTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoLeads;

    private LeadEntityResolver $resolver;

    private ResolutionContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new LeadEntityResolver;
        $this->context = new ResolutionContext(entityType: 'lead_query');
    }

    // ── supports() ───────────────────────────────────────────────────────────

    public function test_supports_lead_query(): void
    {
        $this->assertTrue($this->resolver->supports('lead_query'));
    }

    public function test_does_not_support_other_entity_types(): void
    {
        $this->assertFalse($this->resolver->supports('date'));
        $this->assertFalse($this->resolver->supports('assignee'));
        $this->assertFalse($this->resolver->supports('lead'));
    }

    // ── Exact match — auto-resolve ────────────────────────────────────────────

    public function test_exact_match_rossini_auto_resolves(): void
    {
        $result = $this->resolver->resolve('Rossini', $this->context);

        $this->assertTrue($result->resolved);
        $this->assertNotNull($result->resolvedCandidate);
        $this->assertEquals('Rossini', $result->resolvedCandidate->label);
    }

    public function test_exact_match_is_case_insensitive(): void
    {
        $result = $this->resolver->resolve('rossini', $this->context);

        $this->assertTrue($result->resolved);
        $this->assertEquals('Rossini', $result->resolvedCandidate->label);
    }

    public function test_exact_match_candidate_has_full_confidence(): void
    {
        $result = $this->resolver->resolve('Rossini', $this->context);

        $this->assertEquals(1.0, $result->resolvedCandidate->confidence);
    }

    public function test_exact_match_candidates_list_is_empty(): void
    {
        $result = $this->resolver->resolve('Rossini', $this->context);

        $this->assertEmpty($result->candidates);
    }

    public function test_exact_match_resolved_candidate_has_real_db_id(): void
    {
        $result = $this->resolver->resolve('Rossini', $this->context);

        $expectedId = Lead::where('name', 'Rossini')->value('id');
        $this->assertEquals($expectedId, $result->resolvedCandidate->id);
        $this->assertEquals(5, $result->resolvedCandidate->id);
    }

    // ── Multiple matches — ambiguous ──────────────────────────────────────────

    public function test_rossi_query_is_not_resolved(): void
    {
        $result = $this->resolver->resolve('Rossi', $this->context);

        $this->assertFalse($result->resolved);
        $this->assertNull($result->resolvedCandidate);
    }

    public function test_rossi_query_returns_three_candidates(): void
    {
        $result = $this->resolver->resolve('Rossi', $this->context);

        $this->assertCount(3, $result->candidates);
    }

    public function test_rossi_query_candidates_contain_expected_labels(): void
    {
        $result = $this->resolver->resolve('Rossi', $this->context);

        $labels = array_map(fn ($c) => $c->label, $result->candidates);
        $this->assertContains('Mario Rossi', $labels);
        $this->assertContains('Rossi SRL', $labels);
        $this->assertContains('Studio Rossi', $labels);
    }

    public function test_rossini_is_not_a_candidate_for_rossi_query(): void
    {
        // "Rossini" has no word boundary after "rossi" — must be excluded.
        $result = $this->resolver->resolve('Rossi', $this->context);

        $labels = array_map(fn ($c) => $c->label, $result->candidates);
        $this->assertNotContains('Rossini', $labels);
    }

    public function test_rossi_query_candidates_ordered_by_confidence_descending(): void
    {
        // Rossi SRL scores 0.8 (starts-with) and appears before the two 0.65 matches.
        // Equal-confidence candidates retain DB order (ascending id: Mario before Studio).
        $result = $this->resolver->resolve('Rossi', $this->context);

        $labels = array_map(fn ($c) => $c->label, $result->candidates);
        $this->assertEquals(['Rossi SRL', 'Mario Rossi', 'Studio Rossi'], $labels);
    }

    public function test_rossi_query_candidates_carry_correct_ids_in_sorted_order(): void
    {
        $result = $this->resolver->resolve('Rossi', $this->context);

        $ids = array_map(fn ($c) => $c->id, $result->candidates);
        $this->assertEquals([7, 1, 12], $ids);
    }

    public function test_lead_matched_by_company_field(): void
    {
        // A company-only query resolves the row whose company matches, even though the
        // candidate label is the name — consistent with the executor's name-OR-company lookup.
        Lead::factory()->create(['name' => 'Giovanni Verdi', 'company' => 'VerdiCo']);

        $result = $this->resolver->resolve('VerdiCo', $this->context);

        $this->assertTrue($result->resolved);
        $this->assertEquals('Giovanni Verdi', $result->resolvedCandidate->label);
    }

    // ── Auto-resolve threshold ────────────────────────────────────────────────

    public function test_single_low_confidence_candidate_does_not_auto_resolve(): void
    {
        // A lone word-boundary match (score 0.65) is below the auto-resolve threshold.
        Lead::query()->delete();
        Lead::factory()->create(['name' => 'Mario Rossi', 'company' => null]);

        $result = $this->resolver->resolve('Rossi', $this->context);

        $this->assertFalse($result->resolved);
        $this->assertCount(1, $result->candidates);
        $this->assertNull($result->resolvedCandidate);
    }

    public function test_single_high_confidence_candidate_auto_resolves(): void
    {
        // A lone starts-with match (score 0.8) meets the threshold → auto-resolve.
        Lead::query()->delete();
        Lead::factory()->create(['name' => 'Rossi SRL', 'company' => 'Rossi SRL']);

        $result = $this->resolver->resolve('Rossi', $this->context);

        $this->assertTrue($result->resolved);
        $this->assertEquals('Rossi SRL', $result->resolvedCandidate->label);
    }

    // ── No match ──────────────────────────────────────────────────────────────

    public function test_unrecognised_query_returns_no_match(): void
    {
        $result = $this->resolver->resolve('Bianchi', $this->context);

        $this->assertFalse($result->resolved);
        $this->assertNull($result->resolvedCandidate);
        $this->assertEmpty($result->candidates);
    }

    public function test_empty_query_returns_no_match(): void
    {
        $result = $this->resolver->resolve('', $this->context);

        $this->assertFalse($result->resolved);
        $this->assertEmpty($result->candidates);
    }

    public function test_empty_database_yields_no_hardcoded_candidates(): void
    {
        // Regression: with no Lead rows, the resolver must surface nothing — proving the
        // old in-memory fixtures are gone from the runtime path.
        Lead::query()->delete();

        foreach (['Rossi', 'Rossini', 'Mario Rossi', 'Studio Rossi'] as $query) {
            $result = $this->resolver->resolve($query, $this->context);
            $this->assertFalse($result->resolved, "[{$query}] must not resolve against an empty DB.");
            $this->assertEmpty($result->candidates, "[{$query}] must produce no candidates against an empty DB.");
        }
    }

    // ── Candidate DTO shape ───────────────────────────────────────────────────

    public function test_candidate_to_array_contains_required_keys(): void
    {
        $result = $this->resolver->resolve('Rossi', $this->context);
        $candidate = $result->candidates[0]->toArray();

        $this->assertArrayHasKey('id', $candidate);
        $this->assertArrayHasKey('type', $candidate);
        $this->assertArrayHasKey('label', $candidate);
        $this->assertArrayHasKey('description', $candidate);
        $this->assertArrayHasKey('confidence', $candidate);
    }

    public function test_company_lead_resolves_with_company_type(): void
    {
        $result = $this->resolver->resolve('Rossi', $this->context);

        $rossiSrl = collect($result->candidates)->firstWhere('label', 'Rossi SRL');
        $this->assertNotNull($rossiSrl);
        $this->assertEquals('company', $rossiSrl->type);
    }

    public function test_person_lead_resolves_with_person_type(): void
    {
        $result = $this->resolver->resolve('Rossi', $this->context);

        $mario = collect($result->candidates)->firstWhere('label', 'Mario Rossi');
        $this->assertNotNull($mario);
        $this->assertEquals('person', $mario->type);
    }
}
