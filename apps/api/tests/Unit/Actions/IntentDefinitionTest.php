<?php

namespace Tests\Unit\Actions;

use Fluxio\Actions\DTO\EntityRequirement;
use Fluxio\Actions\DTO\IntentDefinition;
use Fluxio\Actions\Enums\ConfirmationPolicy;
use Fluxio\Actions\Enums\IntentComplexity;
use Fluxio\Actions\Executors\CreateTaskActionExecutor;
use Tests\TestCase;

class IntentDefinitionTest extends TestCase
{
    private function makeDefinition(array $requirements = []): IntentDefinition
    {
        return new IntentDefinition(
            intent:        'test_intent',
            label:         'Test Intent',
            module:        'tasks',
            operation:     'create',
            requirements:  $requirements,
            executorClass: CreateTaskActionExecutor::class,
            confidence:    0.9,
        );
    }

    private function req(string $key, bool $required, string $entityType = 'scalar', string $label = ''): EntityRequirement
    {
        return new EntityRequirement(
            key:        $key,
            entityType: $entityType,
            label:      $label ?: ucfirst($key),
            required:   $required,
        );
    }

    public function test_required_requirements_filters_required_true(): void
    {
        $def = $this->makeDefinition([
            $this->req('lead', true),
            $this->req('date', true),
            $this->req('notes', false),
        ]);

        $keys = array_map(fn ($r) => $r->key, $def->requiredRequirements());
        $this->assertContains('lead', $keys);
        $this->assertContains('date', $keys);
        $this->assertNotContains('notes', $keys);
    }

    public function test_optional_requirements_filters_required_false(): void
    {
        $def = $this->makeDefinition([
            $this->req('lead', true),
            $this->req('notes', false),
            $this->req('priority', false),
        ]);

        $keys = array_map(fn ($r) => $r->key, $def->optionalRequirements());
        $this->assertContains('notes', $keys);
        $this->assertContains('priority', $keys);
        $this->assertNotContains('lead', $keys);
    }

    public function test_required_keys_returns_only_required_keys(): void
    {
        $def = $this->makeDefinition([
            $this->req('lead', true),
            $this->req('date', true),
            $this->req('notes', false),
        ]);

        $this->assertContains('lead', $def->requiredKeys());
        $this->assertContains('date', $def->requiredKeys());
        $this->assertNotContains('notes', $def->requiredKeys());
    }

    public function test_optional_keys_returns_only_optional_keys(): void
    {
        $def = $this->makeDefinition([
            $this->req('lead', true),
            $this->req('notes', false),
        ]);

        $this->assertContains('notes', $def->optionalKeys());
        $this->assertNotContains('lead', $def->optionalKeys());
    }

    public function test_requirement_keys_returns_all_keys(): void
    {
        $def = $this->makeDefinition([
            $this->req('lead', true),
            $this->req('date', true),
            $this->req('notes', false),
        ]);

        $this->assertEquals(['lead', 'date', 'notes'], $def->requirementKeys());
    }

    public function test_find_requirement_returns_matching_object(): void
    {
        $req = new EntityRequirement(key: 'lead', entityType: 'lead_query', label: 'Lead', required: true, resolverRequired: true);
        $def = $this->makeDefinition([$req]);

        $found = $def->findRequirement('lead');
        $this->assertSame($req, $found);
    }

    public function test_find_requirement_returns_null_for_unknown_key(): void
    {
        $def = $this->makeDefinition([$this->req('lead', true)]);
        $this->assertNull($def->findRequirement('nonexistent'));
    }

    public function test_empty_requirements_produce_empty_helper_results(): void
    {
        $def = $this->makeDefinition([]);
        $this->assertEmpty($def->requiredRequirements());
        $this->assertEmpty($def->optionalRequirements());
        $this->assertEmpty($def->requirementKeys());
        $this->assertEmpty($def->requiredKeys());
        $this->assertEmpty($def->optionalKeys());
    }

    public function test_defaults_to_simple_complexity(): void
    {
        $def = $this->makeDefinition();
        $this->assertEquals(IntentComplexity::Simple, $def->complexity);
    }

    public function test_defaults_to_required_confirmation_policy(): void
    {
        $def = $this->makeDefinition();
        $this->assertEquals(ConfirmationPolicy::Required, $def->confirmationPolicy);
    }

    public function test_custom_complexity_is_stored(): void
    {
        $def = new IntentDefinition(
            intent:        'test_domain',
            label:         'Test Domain',
            module:        'leads',
            operation:     'assign',
            requirements:  [],
            executorClass: CreateTaskActionExecutor::class,
            confidence:    0.8,
            complexity:    IntentComplexity::Domain,
        );

        $this->assertEquals(IntentComplexity::Domain, $def->complexity);
    }

    public function test_entity_requirement_stores_all_fields(): void
    {
        $req = new EntityRequirement(
            key:              'participants',
            entityType:       'participant_query',
            label:            'Participants',
            required:         false,
            cardinality:      'many',
            resolverRequired: true,
        );

        $this->assertEquals('participants', $req->key);
        $this->assertEquals('participant_query', $req->entityType);
        $this->assertEquals('Participants', $req->label);
        $this->assertFalse($req->required);
        $this->assertEquals('many', $req->cardinality);
        $this->assertTrue($req->resolverRequired);
    }

    public function test_entity_requirement_defaults_cardinality_to_one(): void
    {
        $req = new EntityRequirement(key: 'lead', entityType: 'lead_query', label: 'Lead', required: true);
        $this->assertEquals('one', $req->cardinality);
        $this->assertFalse($req->resolverRequired);
    }
}
