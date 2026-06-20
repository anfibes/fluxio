<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\EntityResolution\Contracts\EntityResolverInterface;
use Fluxio\Actions\EntityResolution\DTO\ResolutionCandidate;
use Fluxio\Actions\EntityResolution\DTO\ResolutionContext;
use Fluxio\Actions\EntityResolution\DTO\ResolutionResult;
use Fluxio\Actions\EntityResolution\Registry\EntityResolverRegistry;
use Fluxio\Actions\EntityResolution\Resolvers\LeadEntityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SeedsDemoLeads;
use Tests\TestCase;

/**
 * Verifies that EntityResolverRegistry correctly routes queries to registered
 * resolvers and returns no-match results when no resolver handles the type. The
 * LeadEntityResolver is DB-backed, so the demo leads are seeded as real rows.
 */
class EntityResolverRegistryTest extends TestCase
{
    use RefreshDatabase;
    use SeedsDemoLeads;

    private EntityResolverRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new EntityResolverRegistry;
        $this->registry->register(new LeadEntityResolver);
    }

    // ── supports() ───────────────────────────────────────────────────────────

    public function test_registry_supports_lead_query(): void
    {
        $this->assertTrue($this->registry->supports('lead_query'));
    }

    public function test_registry_does_not_support_unknown_type(): void
    {
        $this->assertFalse($this->registry->supports('unknown_entity'));
    }

    // ── resolve() routing ────────────────────────────────────────────────────

    public function test_registry_routes_lead_query_to_lead_resolver(): void
    {
        $result = $this->registry->resolve('Rossini', new ResolutionContext('lead_query'));

        $this->assertTrue($result->resolved);
        $this->assertEquals('Rossini', $result->resolvedCandidate->label);
    }

    public function test_registry_returns_no_match_for_unknown_entity_type(): void
    {
        $result = $this->registry->resolve('anything', new ResolutionContext('unknown_type'));

        $this->assertFalse($result->resolved);
        $this->assertEmpty($result->candidates);
    }

    // ── Multiple resolvers — first match wins ─────────────────────────────────

    public function test_first_matching_resolver_is_used(): void
    {
        // Register a stub that always returns a fixed result for 'lead_query'.
        $stub = new class implements EntityResolverInterface
        {
            public function supports(string $entityType): bool
            {
                return $entityType === 'lead_query';
            }

            public function resolve(string $query, ResolutionContext $context): ResolutionResult
            {
                return ResolutionResult::autoResolved(new ResolutionCandidate(
                    id: 99, type: 'stub', label: 'Stub Lead', description: 'Stub', confidence: 1.0,
                ));
            }
        };

        $registry = new EntityResolverRegistry;
        $registry->register($stub);
        $registry->register(new LeadEntityResolver);

        $result = $registry->resolve('Rossi', new ResolutionContext('lead_query'));

        // Stub wins; LeadEntityResolver is never consulted.
        $this->assertTrue($result->resolved);
        $this->assertEquals('Stub Lead', $result->resolvedCandidate->label);
    }

    // ── ResolutionResult contract ─────────────────────────────────────────────

    public function test_auto_resolved_result_has_resolved_true(): void
    {
        $result = $this->registry->resolve('Rossini', new ResolutionContext('lead_query'));

        $this->assertTrue($result->resolved);
        $this->assertTrue($result->autoResolved);
    }

    public function test_ambiguous_result_has_resolved_false(): void
    {
        $result = $this->registry->resolve('Rossi', new ResolutionContext('lead_query'));

        $this->assertFalse($result->resolved);
        $this->assertFalse($result->autoResolved);
        $this->assertNotEmpty($result->candidates);
    }

    public function test_no_match_result_has_empty_candidates(): void
    {
        $result = $this->registry->resolve('Bianchi', new ResolutionContext('lead_query'));

        $this->assertFalse($result->resolved);
        $this->assertEmpty($result->candidates);
    }
}
