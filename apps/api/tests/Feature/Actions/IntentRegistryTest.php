<?php

namespace Tests\Feature\Actions;

use Fluxio\Actions\DTO\IntentDefinition;
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
        $this->assertContains('lead', $def->requiredEntities);
        $this->assertContains('date', $def->requiredEntities);
        $this->assertContains('time', $def->requiredEntities);
    }

    public function test_assign_lead_required_entities_include_lead_and_assignee(): void
    {
        $def = $this->registry->find('assign_lead');
        $this->assertContains('lead', $def->requiredEntities);
        $this->assertContains('assignee', $def->requiredEntities);
    }

    public function test_prepare_contract_required_entities_include_lead(): void
    {
        $def = $this->registry->find('prepare_contract_from_quote');
        $this->assertContains('lead', $def->requiredEntities);
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
