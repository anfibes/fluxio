<?php

namespace Tests\Feature\Actions;

use App\Models\User;
use Fluxio\Actions\EntityResolution\DTO\ResolutionContext;
use Fluxio\Actions\EntityResolution\Registry\EntityResolverRegistry;
use Fluxio\Actions\EntityResolution\Resolvers\UserEntityResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserEntityResolverTest extends TestCase
{
    use RefreshDatabase;

    private UserEntityResolver $resolver;
    private ResolutionContext  $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new UserEntityResolver;
        $this->context  = new ResolutionContext(entityType: 'user_query');
    }

    // ── supports() ───────────────────────────────────────────────────────────

    public function test_supports_user_query(): void
    {
        $this->assertTrue($this->resolver->supports('user_query'));
    }

    public function test_does_not_support_lead_query(): void
    {
        $this->assertFalse($this->resolver->supports('lead_query'));
    }

    public function test_does_not_support_other_entity_types(): void
    {
        $this->assertFalse($this->resolver->supports('date'));
        $this->assertFalse($this->resolver->supports('assignee'));
    }

    // ── Exact match — auto-resolve ────────────────────────────────────────────

    public function test_exact_name_match_auto_resolves(): void
    {
        User::factory()->create(['name' => 'Marco Bianchi']);

        $result = $this->resolver->resolve('Marco Bianchi', $this->context);

        $this->assertTrue($result->resolved);
        $this->assertNotNull($result->resolvedCandidate);
        $this->assertEquals('Marco Bianchi', $result->resolvedCandidate->label);
    }

    public function test_exact_match_is_case_insensitive(): void
    {
        User::factory()->create(['name' => 'Marco Bianchi']);

        $result = $this->resolver->resolve('marco bianchi', $this->context);

        $this->assertTrue($result->resolved);
        $this->assertEquals('Marco Bianchi', $result->resolvedCandidate->label);
    }

    public function test_exact_match_has_full_confidence(): void
    {
        User::factory()->create(['name' => 'Laura Neri']);

        $result = $this->resolver->resolve('Laura Neri', $this->context);

        $this->assertEquals(1.0, $result->resolvedCandidate->confidence);
    }

    public function test_exact_match_candidate_list_is_empty(): void
    {
        User::factory()->create(['name' => 'Laura Neri']);

        $result = $this->resolver->resolve('Laura Neri', $this->context);

        $this->assertEmpty($result->candidates);
    }

    public function test_single_word_name_auto_resolves_when_exact(): void
    {
        User::factory()->create(['name' => 'Marco']);

        $result = $this->resolver->resolve('Marco', $this->context);

        $this->assertTrue($result->resolved);
        $this->assertEquals('Marco', $result->resolvedCandidate->label);
    }

    // ── Auto-resolve threshold ────────────────────────────────────────────────

    public function test_starts_with_match_auto_resolves_when_single(): void
    {
        // "Marco Rossi" starts with "Marco " → score 0.8 ≥ threshold
        User::factory()->create(['name' => 'Marco Rossi']);

        $result = $this->resolver->resolve('Marco', $this->context);

        $this->assertTrue($result->resolved);
        $this->assertEquals('Marco Rossi', $result->resolvedCandidate->label);
        $this->assertEquals(0.8, $result->resolvedCandidate->confidence);
    }

    public function test_single_low_confidence_candidate_does_not_auto_resolve(): void
    {
        // "Giulia Marco" contains "Marco" at a word boundary → score 0.65 < threshold
        User::factory()->create(['name' => 'Giulia Marco']);

        $result = $this->resolver->resolve('Marco', $this->context);

        $this->assertFalse($result->resolved);
        $this->assertCount(1, $result->candidates);
        $this->assertNull($result->resolvedCandidate);
    }

    // ── Multiple matches — ambiguous ──────────────────────────────────────────

    public function test_multiple_name_matches_are_ambiguous(): void
    {
        User::factory()->create(['name' => 'Marco Rossi']);
        User::factory()->create(['name' => 'Marco Bianchi']);

        $result = $this->resolver->resolve('Marco', $this->context);

        $this->assertFalse($result->resolved);
        $this->assertNull($result->resolvedCandidate);
        $this->assertCount(2, $result->candidates);
    }

    public function test_ambiguous_candidates_ordered_by_confidence_descending(): void
    {
        // "Marco" exactly matches first; the other is starts-with.
        User::factory()->create(['name' => 'Marco']);
        User::factory()->create(['name' => 'Marco Bianchi']);

        $result = $this->resolver->resolve('Marco', $this->context);

        $this->assertFalse($result->resolved);
        $confidences = array_map(fn ($c) => $c->confidence, $result->candidates);
        $this->assertEquals(1.0, $confidences[0]);
        $this->assertEquals(0.8, $confidences[1]);
    }

    // ── No match ──────────────────────────────────────────────────────────────

    public function test_no_match_returns_no_match(): void
    {
        User::factory()->create(['name' => 'Luigi Verdi']);

        $result = $this->resolver->resolve('GhostUser', $this->context);

        $this->assertFalse($result->resolved);
        $this->assertEmpty($result->candidates);
    }

    public function test_empty_query_returns_no_match(): void
    {
        $result = $this->resolver->resolve('', $this->context);

        $this->assertFalse($result->resolved);
        $this->assertEmpty($result->candidates);
    }

    public function test_no_users_in_db_returns_no_match(): void
    {
        $result = $this->resolver->resolve('Marco', $this->context);

        $this->assertFalse($result->resolved);
        $this->assertEmpty($result->candidates);
    }

    // ── Candidate DTO shape ───────────────────────────────────────────────────

    public function test_resolved_candidate_carries_id_type_label_description(): void
    {
        $user = User::factory()->create(['name' => 'Sara Rossi']);

        // Exact match → auto-resolved; check the DTO shape on resolvedCandidate
        $result = $this->resolver->resolve('Sara Rossi', $this->context);
        $this->assertTrue($result->resolved);

        $candidate = $result->resolvedCandidate->toArray();
        $this->assertEquals($user->id, $candidate['id']);
        $this->assertEquals('user', $candidate['type']);
        $this->assertEquals('Sara Rossi', $candidate['label']);
        $this->assertArrayHasKey('description', $candidate); // email
    }

    public function test_ambiguous_candidates_carry_id_type_label_description(): void
    {
        User::factory()->create(['name' => 'Sara Rossi']);
        User::factory()->create(['name' => 'Sara Bianchi']);

        $result = $this->resolver->resolve('Sara', $this->context);
        $this->assertFalse($result->resolved);
        $this->assertCount(2, $result->candidates);

        $candidate = $result->candidates[0]->toArray();
        $this->assertArrayHasKey('id', $candidate);
        $this->assertEquals('user', $candidate['type']);
        $this->assertStringContainsString('Sara', $candidate['label']);
        $this->assertArrayHasKey('description', $candidate); // email
    }

    // ── Registry integration ──────────────────────────────────────────────────

    public function test_registry_routes_user_query_to_user_resolver(): void
    {
        $user = User::factory()->create(['name' => 'Giulia']);

        $registry = $this->app->make(EntityResolverRegistry::class);

        $this->assertTrue($registry->supports('user_query'));

        $result = $registry->resolve('Giulia', new ResolutionContext('user_query'));
        $this->assertTrue($result->resolved);
        $this->assertEquals('Giulia', $result->resolvedCandidate->label);
    }
}
