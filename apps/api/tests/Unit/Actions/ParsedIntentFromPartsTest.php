<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\DTO\EntityReference;
use Fluxio\Actions\DTO\ParsedIntent;
use Fluxio\Actions\Resolvers\RuleBasedIntentResolver;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Phase 9E.3 — producers emit typed EntityReference objects; ParsedIntent::fromParts
 * folds them back into the single entities map (no dual truth, conflict-rejecting),
 * so NormalizedCommand stays byte-identical and the resolver path is typed end-to-end.
 */
class ParsedIntentFromPartsTest extends TestCase
{
    // ── Factory fold ──────────────────────────────────────────────────────────

    public function test_folds_entity_reference_into_entities_map(): void
    {
        $parsed = ParsedIntent::fromParts(
            intent: 'create_task',
            entities: ['date' => '2026-05-11', 'time' => '10:00'],
            entityReferences: [new EntityReference('lead_query', 'Mario Rossi')],
        );

        // The reference is folded into the entities map under its transit key …
        $this->assertSame('Mario Rossi', $parsed->entities['lead_query'] ?? null);
        $this->assertSame('2026-05-11', $parsed->entities['date'] ?? null);
        $this->assertSame('10:00', $parsed->entities['time'] ?? null);

        // … and is also readable as a typed reference (single source of truth).
        $ref = $parsed->entityReference('lead_query');
        $this->assertNotNull($ref);
        $this->assertSame('Mario Rossi', $ref->value);
    }

    public function test_references_are_folded_first_preserving_key_order(): void
    {
        $parsed = ParsedIntent::fromParts(
            intent: 'create_task',
            entities: ['date' => '2026-05-11', 'time' => '10:00'],
            entityReferences: [new EntityReference('lead_query', 'Mario Rossi')],
        );

        // lead_query first, then the parsed values — matches the prior direct build.
        $this->assertSame(['lead_query', 'date', 'time'], array_keys($parsed->entities));
    }

    public function test_empty_references_behaves_like_plain_construction(): void
    {
        $parsed = ParsedIntent::fromParts('create_task', ['date' => '2026-05-11']);

        $this->assertSame(['date' => '2026-05-11'], $parsed->entities);
        $this->assertSame([], $parsed->entityReferences());
    }

    // ── No dual truth / conflict behaviour ────────────────────────────────────

    public function test_matching_value_is_idempotent(): void
    {
        // entities already holds lead_query with the SAME value the reference carries.
        $parsed = ParsedIntent::fromParts(
            intent: 'schedule_call',
            entities: ['lead_query' => 'Rossi'],
            entityReferences: [new EntityReference('lead_query', 'Rossi')],
        );

        $this->assertSame('Rossi', $parsed->entities['lead_query']);
        $this->assertCount(1, $parsed->entityReferences());
    }

    public function test_conflicting_value_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ParsedIntent::fromParts(
            intent: 'schedule_call',
            entities: ['lead_query' => 'Rossi'],
            entityReferences: [new EntityReference('lead_query', 'Mario Rossi')],
        );
    }

    public function test_non_entity_reference_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore-next-line — intentional misuse for the guard */
        ParsedIntent::fromParts('create_task', [], [], ['not-a-reference']);
    }

    // ── Resolver production path ──────────────────────────────────────────────

    public function test_resolver_produces_typed_entity_reference(): void
    {
        /** @var RuleBasedIntentResolver $resolver */
        $resolver = $this->app->make(RuleBasedIntentResolver::class);

        $parsed = $resolver->resolve('Create a high priority follow-up task for Mario Rossi tomorrow at 10');

        // Typed reference is available …
        $ref = $parsed->entityReference('lead_query');
        $this->assertNotNull($ref);
        $this->assertSame('lead_query', $ref->key);
        $this->assertSame('Mario Rossi', $ref->value);

        // … and the wire bridge is preserved (entities map still carries lead_query),
        // with no resolved identity leaking from the interpreter.
        $this->assertSame('Mario Rossi', $parsed->entities['lead_query'] ?? null);
        $this->assertArrayNotHasKey('lead', $parsed->entities);
        $this->assertArrayNotHasKey('lead_id', $parsed->entities);
        $this->assertArrayNotHasKey('selected_candidate_id', $parsed->entities);
    }

    public function test_resolver_short_reference_remains_a_reference_span(): void
    {
        /** @var RuleBasedIntentResolver $resolver */
        $resolver = $this->app->make(RuleBasedIntentResolver::class);

        $parsed = $resolver->resolve('Schedule a call with Rossi tomorrow at 10');

        $this->assertSame('Rossi', $parsed->entityReference('lead_query')?->value);
        $this->assertSame('10:00', $parsed->entities['time'] ?? null);
    }
}
