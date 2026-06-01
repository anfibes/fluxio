<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\DTO\EntityReference;
use Fluxio\Actions\DTO\ParsedIntent;
use Fluxio\Actions\Interpreters\RuleBasedCommandInterpreter;
use Tests\TestCase;

/**
 * Phase 9E.2 — ParsedIntent exposes the entity reference category (the `*_query`
 * spans) as typed EntityReference objects, distinct from already-parsed scalar
 * values. This is a derived, read-side view: storage and the NormalizedCommand wire
 * shape are unchanged, so the behavior is byte-identical.
 */
class ParsedIntentEntityReferenceTest extends TestCase
{
    public function test_exposes_lead_query_as_an_entity_reference(): void
    {
        $parsed = new ParsedIntent('create_task', [
            'lead_query' => 'Mario Rossi',
            'date' => '2026-05-11',
            'time' => '10:00',
        ]);

        $references = $parsed->entityReferences();

        $this->assertCount(1, $references);
        $this->assertInstanceOf(EntityReference::class, $references[0]);
        $this->assertSame('lead_query', $references[0]->key);
        $this->assertSame('Mario Rossi', $references[0]->value);

        $ref = $parsed->entityReference('lead_query');
        $this->assertNotNull($ref);
        $this->assertSame('Mario Rossi', $ref->value);
    }

    public function test_parsed_scalar_values_are_not_entity_references(): void
    {
        $parsed = new ParsedIntent('assign_lead', [
            'lead_query' => 'Rossi',
            'assignee' => 'Marco',
            'date' => '2026-05-11',
            'time' => '10:00',
            'priority' => 'high',
        ]);

        $keys = array_map(static fn (EntityReference $r) => $r->key, $parsed->entityReferences());

        $this->assertSame(['lead_query'], $keys);
        $this->assertNull($parsed->entityReference('assignee'));
        $this->assertNull($parsed->entityReference('date'));
        $this->assertNull($parsed->entityReference('time'));
        $this->assertNull($parsed->entityReference('priority'));
    }

    public function test_no_references_when_only_parsed_values_present(): void
    {
        $parsed = new ParsedIntent('create_task', ['date' => '2026-05-11']);

        $this->assertSame([], $parsed->entityReferences());
        $this->assertNull($parsed->entityReference('lead_query'));
    }

    public function test_normalized_command_entities_remain_unchanged(): void
    {
        /** @var RuleBasedCommandInterpreter $interpreter */
        $interpreter = $this->app->make(RuleBasedCommandInterpreter::class);

        $command = $interpreter->interpret('Create a follow-up task for Mario Rossi tomorrow at 10');

        // The reference span still lives in the entities map under lead_query — the
        // typed view is additive and does not alter the wire shape.
        $this->assertSame('Mario Rossi', $command->entities['lead_query'] ?? null);
        $this->assertArrayNotHasKey('lead', $command->entities);
        $this->assertArrayNotHasKey('lead_id', $command->entities);
        $this->assertSame('10:00', $command->entities['time'] ?? null);
    }
}
