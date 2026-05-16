<?php

namespace Tests\Feature\Actions;

use Fluxio\Actions\DTO\EntityRequirement;
use Fluxio\Actions\DTO\IntentDefinition;
use Fluxio\Actions\Enums\ConfirmationPolicy;
use Fluxio\Actions\Enums\IntentComplexity;
use Fluxio\Actions\Executors\CreateTaskActionExecutor;
use Fluxio\Actions\Registry\IntentRegistry;
use Tests\TestCase;

class IntentRegistryTest extends TestCase
{
    private IntentRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = $this->app->make(IntentRegistry::class);
    }

    public function test_create_task_is_registered(): void
    {
        $this->assertNotNull($this->registry->find('create_task'));
    }

    public function test_schedule_call_is_registered(): void
    {
        $this->assertNotNull($this->registry->find('schedule_call'));
    }

    public function test_schedule_meeting_is_registered(): void
    {
        $this->assertNotNull($this->registry->find('schedule_meeting'));
    }

    public function test_assign_lead_is_registered(): void
    {
        $this->assertNotNull($this->registry->find('assign_lead'));
    }

    public function test_prepare_contract_from_quote_is_registered(): void
    {
        $this->assertNotNull($this->registry->find('prepare_contract_from_quote'));
    }

    public function test_unknown_intent_returns_null(): void
    {
        $this->assertNull($this->registry->find('unknown'));
        $this->assertNull($this->registry->find('does_not_exist'));
    }

    public function test_all_returns_all_registered_definitions(): void
    {
        $all = $this->registry->all();
        $this->assertArrayHasKey('create_task', $all);
        $this->assertArrayHasKey('schedule_call', $all);
        $this->assertArrayHasKey('schedule_meeting', $all);
        $this->assertArrayHasKey('assign_lead', $all);
        $this->assertArrayHasKey('prepare_contract_from_quote', $all);
    }

    public function test_create_task_has_correct_confidence(): void
    {
        $def = $this->registry->find('create_task');
        $this->assertEquals(0.9, $def->confidence);
    }

    public function test_schedule_call_has_correct_confidence(): void
    {
        $def = $this->registry->find('schedule_call');
        $this->assertEquals(0.7, $def->confidence);
    }

    public function test_assign_lead_has_correct_confidence(): void
    {
        $def = $this->registry->find('assign_lead');
        $this->assertEquals(0.8, $def->confidence);
    }

    public function test_prepare_contract_has_correct_confidence(): void
    {
        $def = $this->registry->find('prepare_contract_from_quote');
        $this->assertEquals(0.75, $def->confidence);
    }

    public function test_schedule_call_required_entities_include_lead_date_time(): void
    {
        $def = $this->registry->find('schedule_call');
        $this->assertContains('lead', $def->requiredKeys());
        $this->assertContains('date', $def->requiredKeys());
        $this->assertContains('time', $def->requiredKeys());
    }

    public function test_assign_lead_required_entities_include_lead_and_assignee(): void
    {
        $def = $this->registry->find('assign_lead');
        $this->assertContains('lead', $def->requiredKeys());
        $this->assertContains('assignee', $def->requiredKeys());
    }

    public function test_prepare_contract_required_entities_include_lead(): void
    {
        $def = $this->registry->find('prepare_contract_from_quote');
        $this->assertContains('lead', $def->requiredKeys());
    }

    public function test_schedule_call_requirements_are_entity_requirement_objects(): void
    {
        $def = $this->registry->find('schedule_call');
        foreach ($def->requirements as $req) {
            $this->assertInstanceOf(EntityRequirement::class, $req);
        }
    }

    public function test_schedule_call_optional_keys_include_participants(): void
    {
        $def = $this->registry->find('schedule_call');
        $this->assertContains('participants', $def->optionalKeys());
    }

    public function test_create_task_lead_is_optional(): void
    {
        $def = $this->registry->find('create_task');
        $this->assertContains('lead', $def->optionalKeys());
        $this->assertNotContains('lead', $def->requiredKeys());
    }

    public function test_find_requirement_returns_correct_object(): void
    {
        $def = $this->registry->find('schedule_meeting');
        $req = $def->findRequirement('lead');
        $this->assertNotNull($req);
        $this->assertEquals('lead', $req->key);
        $this->assertEquals('Lead', $req->label);
        $this->assertTrue($req->required);
        $this->assertTrue($req->resolverRequired);
    }

    public function test_find_requirement_returns_null_for_unknown_key(): void
    {
        $def = $this->registry->find('create_task');
        $this->assertNull($def->findRequirement('nonexistent'));
    }

    public function test_assign_lead_has_domain_complexity(): void
    {
        $def = $this->registry->find('assign_lead');
        $this->assertEquals(IntentComplexity::Domain, $def->complexity);
    }

    public function test_simple_intents_have_simple_complexity(): void
    {
        foreach (['create_task', 'schedule_call', 'schedule_meeting', 'prepare_contract_from_quote'] as $intent) {
            $def = $this->registry->find($intent);
            $this->assertEquals(IntentComplexity::Simple, $def->complexity, "Expected simple complexity for intent: {$intent}");
        }
    }

    public function test_all_intents_have_required_confirmation_policy_by_default(): void
    {
        foreach ($this->registry->all() as $def) {
            $this->assertEquals(ConfirmationPolicy::Required, $def->confirmationPolicy, "Expected required policy for intent: {$def->intent}");
        }
    }

    public function test_schedule_meeting_participants_has_many_cardinality(): void
    {
        $def = $this->registry->find('schedule_meeting');
        $req = $def->findRequirement('participants');
        $this->assertNotNull($req);
        $this->assertEquals('many', $req->cardinality);
    }

    public function test_lead_resolver_required_on_lead_bearing_intents(): void
    {
        foreach (['schedule_call', 'schedule_meeting', 'assign_lead', 'prepare_contract_from_quote'] as $intent) {
            $def = $this->registry->find($intent);
            $req = $def->findRequirement('lead');
            $this->assertNotNull($req, "Expected lead requirement on {$intent}");
            $this->assertTrue($req->resolverRequired, "Expected resolverRequired=true for lead on {$intent}");
        }
    }

    public function test_executor_resolved_for_create_task(): void
    {
        $executor = $this->registry->resolveExecutor('create_task');
        $this->assertInstanceOf(CreateTaskActionExecutor::class, $executor);
    }

    public function test_executor_resolution_throws_for_unknown_intent(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->registry->resolveExecutor('unknown');
    }

    public function test_definitions_have_needs_confirmation_true(): void
    {
        foreach ($this->registry->all() as $def) {
            $this->assertTrue($def->needsConfirmation, "Expected needsConfirmation=true for intent: {$def->intent}");
        }
    }
}
