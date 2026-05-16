<?php

namespace Fluxio\Actions\Providers;

use Fluxio\Actions\Contracts\CommandInterpreterInterface;
use Fluxio\Actions\Contracts\IntentResolverInterface;
use Fluxio\Actions\Contracts\RefinementInterpreterInterface;
use Fluxio\Actions\DTO\IntentDefinition;
use Fluxio\Actions\EntityResolution\Registry\EntityResolverRegistry;
use Fluxio\Actions\EntityResolution\Resolvers\LeadEntityResolver;
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
                intent:           'create_task',
                label:            'Create Task',
                module:           'tasks',
                operation:        'create',
                requiredEntities: [],
                optionalEntities: ['lead', 'priority', 'due_at'],
                executorClass:    CreateTaskActionExecutor::class,
                confidence:       0.9,
            ));

            $registry->register(new IntentDefinition(
                intent:           'schedule_call',
                label:            'Schedule Call',
                module:           'calendar',
                operation:        'schedule',
                requiredEntities: ['lead', 'date', 'time'],
                optionalEntities: ['participants', 'priority'],
                executorClass:    ScheduleCallActionExecutor::class,
                confidence:       0.7,
            ));

            $registry->register(new IntentDefinition(
                intent:           'schedule_meeting',
                label:            'Schedule Meeting',
                module:           'calendar',
                operation:        'schedule',
                requiredEntities: ['lead', 'date', 'time'],
                optionalEntities: ['participants', 'location'],
                executorClass:    ScheduleMeetingActionExecutor::class,
                confidence:       0.7,
            ));

            $registry->register(new IntentDefinition(
                intent:           'assign_lead',
                label:            'Assign Lead',
                module:           'leads',
                operation:        'assign',
                requiredEntities: ['lead', 'assignee'],
                optionalEntities: [],
                executorClass:    AssignLeadActionExecutor::class,
                confidence:       0.8,
            ));

            $registry->register(new IntentDefinition(
                intent:           'prepare_contract_from_quote',
                label:            'Prepare Contract',
                module:           'tasks',
                operation:        'create',
                requiredEntities: ['lead'],
                optionalEntities: ['quote'],
                executorClass:    PrepareContractActionExecutor::class,
                confidence:       0.75,
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
