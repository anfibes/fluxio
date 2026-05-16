<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\EntityResolution\DTO\ResolutionContext;
use Fluxio\Actions\EntityResolution\Repositories\InMemoryLeadRepository;
use Fluxio\Actions\EntityResolution\Resolvers\LeadEntityResolver;
use Tests\TestCase;

/**
 * Verifies that LeadEntityResolver produces correct ResolutionResult values
 * for exact matches, ambiguous multi-matches, and unrecognised queries.
 */
class LeadEntityResolverTest extends TestCase
{
    private LeadEntityResolver $resolver;
    private ResolutionContext  $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new LeadEntityResolver(new InMemoryLeadRepository());
        $this->context  = new ResolutionContext(entityType: 'lead_query');
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

    public function test_exact_match_resolved_candidate_has_correct_id(): void
    {
        $result = $this->resolver->resolve('Rossini', $this->context);

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
        // Rossi SRL scores 0.8 (starts-with) and should appear before the two 0.65 matches.
        // Equal-confidence candidates (Mario Rossi, Studio Rossi) retain repository order.
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

    // ── Auto-resolve threshold ────────────────────────────────────────────────

    public function test_single_low_confidence_candidate_does_not_auto_resolve(): void
    {
        // A lone word-boundary match (score 0.65) is below the auto-resolve threshold
        // and must surface as an ambiguity requiring explicit user selection.
        $repo     = new InMemoryLeadRepository([
            ['id' => 1, 'type' => 'person', 'label' => 'Mario Rossi', 'description' => null],
        ]);
        $resolver = new LeadEntityResolver($repo);

        $result = $resolver->resolve('Rossi', $this->context);

        $this->assertFalse($result->resolved);
        $this->assertCount(1, $result->candidates);
        $this->assertNull($result->resolvedCandidate);
    }

    public function test_single_high_confidence_candidate_auto_resolves(): void
    {
        // A lone starts-with match (score 0.8) meets the threshold → auto-resolve.
        $repo     = new InMemoryLeadRepository([
            ['id' => 7, 'type' => 'company', 'label' => 'Rossi SRL', 'description' => 'Company lead'],
        ]);
        $resolver = new LeadEntityResolver($repo);

        $result = $resolver->resolve('Rossi', $this->context);

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

    // ── Candidate DTO shape ───────────────────────────────────────────────────

    public function test_candidate_to_array_contains_required_keys(): void
    {
        $result    = $this->resolver->resolve('Rossi', $this->context);
        $candidate = $result->candidates[0]->toArray();

        $this->assertArrayHasKey('id', $candidate);
        $this->assertArrayHasKey('type', $candidate);
        $this->assertArrayHasKey('label', $candidate);
        $this->assertArrayHasKey('description', $candidate);
        $this->assertArrayHasKey('confidence', $candidate);
    }

    public function test_candidate_description_may_be_null(): void
    {
        // ResolutionCandidate accepts nullable description — the payload key must
        // still be present in toArray() even when the value is null.
        $repo      = new InMemoryLeadRepository([
            ['id' => 99, 'type' => 'person', 'label' => 'Rossini', 'description' => null],
        ]);
        $resolver  = new LeadEntityResolver($repo);
        $result    = $resolver->resolve('Rossini', $this->context);
        $candidate = $result->resolvedCandidate->toArray();

        $this->assertArrayHasKey('description', $candidate);
        $this->assertNull($candidate['description']);
    }
}
