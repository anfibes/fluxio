<?php

namespace Fluxio\Actions\Providers;

use Fluxio\Actions\Contracts\CommandInterpreterInterface;
use Fluxio\Actions\Contracts\IntentResolverInterface;
use Fluxio\Actions\Contracts\RefinementInterpreterInterface;
use Fluxio\Actions\DTO\EntityRequirement;
use Fluxio\Actions\DTO\IntentDefinition;
use Fluxio\Actions\EntityResolution\Registry\EntityResolverRegistry;
use Fluxio\Actions\EntityResolution\Resolvers\LeadEntityResolver;
use Fluxio\Actions\Enums\IntentComplexity;
use Fluxio\Actions\Executors\AssignLeadActionExecutor;
use Fluxio\Actions\Executors\CreateTaskActionExecutor;
use Fluxio\Actions\Executors\PrepareContractActionExecutor;
use Fluxio\Actions\Executors\ScheduleCallActionExecutor;
use Fluxio\Actions\Executors\ScheduleMeetingActionExecutor;
use Fluxio\Actions\Interpreters\RuleBasedCommandInterpreter;
use Fluxio\Actions\Interpreters\RuleBasedRefinementInterpreter;
use Fluxio\Actions\Registry\IntentRegistry;
use Fluxio\Actions\Resolvers\RuleBasedIntentResolver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ActionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Entity resolver registry — routes entity queries to the correct resolver
        $this->app->singleton(EntityResolverRegistry::class, function ($app) {
            $registry = new EntityResolverRegistry();
            $registry->register($app->make(LeadEntityResolver::class));

            return $registry;
        });

        // Intent registry — single source of truth for intent metadata
        $this->app->singleton(IntentRegistry::class, function () {
            $registry = new IntentRegistry();

            $registry->register(new IntentDefinition(
                intent:        'create_task',
                label:         'Create Task',
                module:        'tasks',
                operation:     'create',
                requirements:  [
                    new EntityRequirement(key: 'lead',     entityType: 'lead_query',       label: 'Lead',     required: false, resolverRequired: true),
                    new EntityRequirement(key: 'priority', entityType: 'scalar',            label: 'Priority', required: false),
                    new EntityRequirement(key: 'due_at',   entityType: 'date_expression',   label: 'Due Date', required: false),
                ],
                executorClass: CreateTaskActionExecutor::class,
                confidence:    0.9,
            ));

            $registry->register(new IntentDefinition(
                intent:        'schedule_call',
                label:         'Schedule Call',
                module:        'calendar',
                operation:     'schedule',
                requirements:  [
                    new EntityRequirement(key: 'lead',         entityType: 'lead_query',        label: 'Lead',         required: true,  resolverRequired: true),
                    new EntityRequirement(key: 'date',         entityType: 'date_expression',   label: 'Date',         required: true),
                    new EntityRequirement(key: 'time',         entityType: 'time_expression',   label: 'Time',         required: true),
                    new EntityRequirement(key: 'participants', entityType: 'participant_query',  label: 'Participants', required: false, cardinality: 'many'),
                    new EntityRequirement(key: 'priority',     entityType: 'scalar',            label: 'Priority',     required: false),
                ],
                executorClass: ScheduleCallActionExecutor::class,
                confidence:    0.7,
            ));

            $registry->register(new IntentDefinition(
                intent:        'schedule_meeting',
                label:         'Schedule Meeting',
                module:        'calendar',
                operation:     'schedule',
                requirements:  [
                    new EntityRequirement(key: 'lead',         entityType: 'lead_query',       label: 'Lead',         required: true,  resolverRequired: true),
                    new EntityRequirement(key: 'date',         entityType: 'date_expression',  label: 'Date',         required: true),
                    new EntityRequirement(key: 'time',         entityType: 'time_expression',  label: 'Time',         required: true),
                    new EntityRequirement(key: 'participants', entityType: 'participant_query', label: 'Participants', required: false, cardinality: 'many'),
                    new EntityRequirement(key: 'location',     entityType: 'scalar',           label: 'Location',     required: false),
                ],
                executorClass: ScheduleMeetingActionExecutor::class,
                confidence:    0.7,
            ));

            $registry->register(new IntentDefinition(
                intent:        'assign_lead',
                label:         'Assign Lead',
                module:        'leads',
                operation:     'assign',
                requirements:  [
                    new EntityRequirement(key: 'lead',     entityType: 'lead_query',  label: 'Lead',     required: true, resolverRequired: true),
                    new EntityRequirement(key: 'assignee', entityType: 'user_query',  label: 'Assignee', required: true),
                ],
                executorClass: AssignLeadActionExecutor::class,
                confidence:    0.8,
                complexity:    IntentComplexity::Domain,
            ));

            $registry->register(new IntentDefinition(
                intent:        'prepare_contract_from_quote',
                label:         'Prepare Contract',
                module:        'tasks',
                operation:     'create',
                requirements:  [
                    new EntityRequirement(key: 'lead',  entityType: 'lead_query', label: 'Lead',  required: true,  resolverRequired: true),
                    new EntityRequirement(key: 'quote', entityType: 'scalar',     label: 'Quote', required: false),
                ],
                executorClass: PrepareContractActionExecutor::class,
                confidence:    0.75,
            ));

            return $registry;
        });

        // Low-level resolver — still bound for direct consumers and for RuleBasedCommandInterpreter
        $this->app->bind(IntentResolverInterface::class, RuleBasedIntentResolver::class);

        // Normalized interpretation boundaries
        $this->app->bind(CommandInterpreterInterface::class, RuleBasedCommandInterpreter::class);
        $this->app->bind(RefinementInterpreterInterface::class, RuleBasedRefinementInterpreter::class);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/actions')
            ->group(__DIR__ . '/../../routes/api.php');

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../../lang', 'actions');
    }
}
