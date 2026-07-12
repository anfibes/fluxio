<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\EntityResolution\DTO\ResolutionContext;
use Fluxio\Actions\EntityResolution\Registry\EntityResolverRegistry;
use Fluxio\Actions\EntityResolution\Resolvers\TaskEntityResolver;
use Fluxio\Tasks\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskEntityResolverTest extends TestCase
{
    use RefreshDatabase;

    private TaskEntityResolver $resolver;

    private ResolutionContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new TaskEntityResolver;
        $this->context = new ResolutionContext(entityType: 'task_query');
    }

    // ── supports() ───────────────────────────────────────────────────────────

    public function test_supports_task_query(): void
    {
        $this->assertTrue($this->resolver->supports('task_query'));
    }

    public function test_does_not_support_other_entity_types(): void
    {
        $this->assertFalse($this->resolver->supports('lead_query'));
        $this->assertFalse($this->resolver->supports('user_query'));
    }

    // ── Exact match — auto-resolve ────────────────────────────────────────────

    public function test_exact_title_match_auto_resolves(): void
    {
        Task::factory()->create(['title' => 'Follow-up Rossi']);

        $result = $this->resolver->resolve('Follow-up Rossi', $this->context);

        $this->assertTrue($result->resolved);
        $this->assertNotNull($result->resolvedCandidate);
        $this->assertEquals('Follow-up Rossi', $result->resolvedCandidate->label);
        $this->assertEquals(1.0, $result->resolvedCandidate->confidence);
        $this->assertEquals('task', $result->resolvedCandidate->type);
    }

    public function test_exact_match_is_case_insensitive(): void
    {
        Task::factory()->create(['title' => 'Follow-up Rossi']);

        $result = $this->resolver->resolve('follow-up rossi', $this->context);

        $this->assertTrue($result->resolved);
        $this->assertEquals('Follow-up Rossi', $result->resolvedCandidate->label);
    }

    public function test_starts_with_match_auto_resolves_when_single(): void
    {
        Task::factory()->create(['title' => 'Rossi follow-up call']);

        $result = $this->resolver->resolve('Rossi', $this->context);

        $this->assertTrue($result->resolved);
        $this->assertEquals(0.8, $result->resolvedCandidate->confidence);
    }

    public function test_single_low_confidence_candidate_does_not_auto_resolve(): void
    {
        // Word-boundary match inside the title → 0.65 < threshold.
        Task::factory()->create(['title' => 'Call the Rossi lead']);

        $result = $this->resolver->resolve('Rossi', $this->context);

        $this->assertFalse($result->resolved);
        $this->assertCount(1, $result->candidates);
    }

    // ── Multiple matches — ambiguous ──────────────────────────────────────────

    public function test_multiple_title_matches_are_ambiguous(): void
    {
        Task::factory()->create(['title' => 'Follow-up Rossi']);
        Task::factory()->create(['title' => 'Follow-up Bianchi']);

        $result = $this->resolver->resolve('Follow-up', $this->context);

        $this->assertFalse($result->resolved);
        $this->assertNull($result->resolvedCandidate);
        $this->assertCount(2, $result->candidates);
    }

    public function test_equal_confidence_candidates_are_ordered_by_ascending_id(): void
    {
        // Three homonymous tasks → identical confidence (exact match, 1.0). The
        // base row order must be deterministic (orderBy id) so stable usort keeps
        // ascending-id order and ordinal selectors stay stable across requests.
        $created = [
            Task::factory()->create(['title' => 'Follow-up']),
            Task::factory()->create(['title' => 'Follow-up']),
            Task::factory()->create(['title' => 'Follow-up']),
        ];

        // Expected order derived from the REAL persisted keys, sorted ascending —
        // no hardcoded ids, no reliance on creation/collection order.
        $expectedIds = collect($created)->pluck('id')->sort()->values()->all();

        $result = $this->resolver->resolve('Follow-up', $this->context);

        $this->assertFalse($result->resolved);
        $this->assertCount(3, $result->candidates);

        $confidences = array_map(fn ($c) => $c->confidence, $result->candidates);
        $this->assertSame([1.0, 1.0, 1.0], $confidences, 'Homonyms must tie on confidence for this test to be meaningful.');

        $this->assertSame(
            $expectedIds,
            array_map(fn ($c) => $c->id, $result->candidates),
            'Equal-confidence candidates must surface in ascending-id order.',
        );
    }

    // ── No match ──────────────────────────────────────────────────────────────

    public function test_no_match_returns_no_match(): void
    {
        Task::factory()->create(['title' => 'Prepare quote']);

        $result = $this->resolver->resolve('Nonexistent', $this->context);

        $this->assertFalse($result->resolved);
        $this->assertEmpty($result->candidates);
    }

    public function test_empty_query_returns_no_match(): void
    {
        $result = $this->resolver->resolve('', $this->context);

        $this->assertFalse($result->resolved);
        $this->assertEmpty($result->candidates);
    }

    public function test_no_tasks_in_db_returns_no_match(): void
    {
        $result = $this->resolver->resolve('Follow-up', $this->context);

        $this->assertFalse($result->resolved);
        $this->assertEmpty($result->candidates);
    }

    // ── Registry integration ──────────────────────────────────────────────────

    public function test_registry_routes_task_query_to_task_resolver(): void
    {
        Task::factory()->create(['title' => 'Follow-up Rossi']);

        $registry = $this->app->make(EntityResolverRegistry::class);

        $this->assertTrue($registry->supports('task_query'));

        $result = $registry->resolve('Follow-up Rossi', new ResolutionContext('task_query'));
        $this->assertTrue($result->resolved);
        $this->assertEquals('Follow-up Rossi', $result->resolvedCandidate->label);
    }
}
