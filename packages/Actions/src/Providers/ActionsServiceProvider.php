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
use Fluxio\Actions\Interpretation\Contracts\InterpretationProviderInterface;
use Fluxio\Actions\Interpretation\InterpretationProviderAdapter;
use Fluxio\Actions\Interpretation\Providers\DeterministicInterpretationProvider;
use Fluxio\Actions\Interpretation\Providers\OllamaInterpretationProvider;
use Fluxio\Actions\Interpreters\RuleBasedRefinementInterpreter;
use Fluxio\Actions\Llm\Clients\OllamaLlmClient;
use Fluxio\Actions\Llm\Contracts\LlmClientInterface;
use Fluxio\Actions\Llm\Prompting\InterpretationPromptBuilder;
use Fluxio\Actions\Llm\Validation\LlmStructuredOutputValidator;
use Fluxio\Actions\Registry\IntentCapabilityRegistry;
use Fluxio\Actions\Registry\IntentRegistry;
use Fluxio\Actions\Resolvers\RuleBasedIntentResolver;
use Fluxio\Actions\Support\DefaultIntentCapabilities;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class ActionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/actions.php', 'actions');

        // LLM transport boundary — generic client abstraction for future
        // LLM-backed interpretation providers. Not wired into the proposal
        // runtime; the deterministic provider remains authoritative.
        $this->app->bind(LlmClientInterface::class, function ($app) {
            $config = $app['config']->get('actions.llm', []);

            $provider = (string) ($config['provider'] ?? 'ollama');

            if ($provider !== 'ollama') {
                throw new InvalidArgumentException(
                    "Unsupported Actions LLM provider [{$provider}]."
                );
            }

            return new OllamaLlmClient(
                http: $app->make(HttpFactory::class),
                baseUrl: (string) ($config['base_url'] ?? 'http://127.0.0.1:11434'),
                defaultModel: (string) ($config['model'] ?? 'qwen3:0.6b'),
                timeout: (int) ($config['timeout'] ?? 10),
            );
        });

        // Entity resolver registry — routes entity queries to the correct resolver
        $this->app->singleton(EntityResolverRegistry::class, function ($app) {
            $registry = new EntityResolverRegistry;
            $registry->register($app->make(LeadEntityResolver::class));

            return $registry;
        });

        // Intent registry — single source of truth for intent metadata
        $this->app->singleton(IntentRegistry::class, function () {
            $registry = new IntentRegistry;

            $registry->register(new IntentDefinition(
                intent: 'create_task',
                label: 'Create Task',
                module: 'tasks',
                operation: 'create',
                requirements: [
                    new EntityRequirement(key: 'lead', entityType: 'lead_query', label: 'Lead', required: false, resolverRequired: true),
                    new EntityRequirement(key: 'priority', entityType: 'scalar', label: 'Priority', required: false),
                    new EntityRequirement(key: 'due_at', entityType: 'date_expression', label: 'Due Date', required: false),
                ],
                executorClass: CreateTaskActionExecutor::class,
                confidence: 0.9,
            ));

            $registry->register(new IntentDefinition(
                intent: 'schedule_call',
                label: 'Schedule Call',
                module: 'calendar',
                operation: 'schedule',
                requirements: [
                    new EntityRequirement(key: 'lead', entityType: 'lead_query', label: 'Lead', required: true, resolverRequired: true),
                    new EntityRequirement(key: 'date', entityType: 'date_expression', label: 'Date', required: true),
                    new EntityRequirement(key: 'time', entityType: 'time_expression', label: 'Time', required: true),
                    new EntityRequirement(key: 'participants', entityType: 'participant_query', label: 'Participants', required: false, cardinality: 'many'),
                    new EntityRequirement(key: 'priority', entityType: 'scalar', label: 'Priority', required: false),
                ],
                executorClass: ScheduleCallActionExecutor::class,
                confidence: 0.7,
            ));

            $registry->register(new IntentDefinition(
                intent: 'schedule_meeting',
                label: 'Schedule Meeting',
                module: 'calendar',
                operation: 'schedule',
                requirements: [
                    new EntityRequirement(key: 'lead', entityType: 'lead_query', label: 'Lead', required: true, resolverRequired: true),
                    new EntityRequirement(key: 'date', entityType: 'date_expression', label: 'Date', required: true),
                    new EntityRequirement(key: 'time', entityType: 'time_expression', label: 'Time', required: true),
                    new EntityRequirement(key: 'participants', entityType: 'participant_query', label: 'Participants', required: false, cardinality: 'many'),
                    new EntityRequirement(key: 'location', entityType: 'scalar', label: 'Location', required: false),
                ],
                executorClass: ScheduleMeetingActionExecutor::class,
                confidence: 0.7,
            ));

            $registry->register(new IntentDefinition(
                intent: 'assign_lead',
                label: 'Assign Lead',
                module: 'leads',
                operation: 'assign',
                requirements: [
                    new EntityRequirement(key: 'lead', entityType: 'lead_query', label: 'Lead', required: true, resolverRequired: true),
                    new EntityRequirement(key: 'assignee', entityType: 'user_query', label: 'Assignee', required: true),
                ],
                executorClass: AssignLeadActionExecutor::class,
                confidence: 0.8,
                complexity: IntentComplexity::Domain,
            ));

            $registry->register(new IntentDefinition(
                intent: 'prepare_contract_from_quote',
                label: 'Prepare Contract',
                module: 'tasks',
                operation: 'create',
                requirements: [
                    new EntityRequirement(key: 'lead', entityType: 'lead_query', label: 'Lead', required: true, resolverRequired: true),
                    new EntityRequirement(key: 'quote', entityType: 'scalar', label: 'Quote', required: false),
                ],
                executorClass: PrepareContractActionExecutor::class,
                confidence: 0.75,
            ));

            return $registry;
        });

        // Intent capability registry — static, in-memory declaration of what each intent permits.
        // Consulted by ActionProposalRefinementService before applying any mutation.
        // Capability definitions live in DefaultIntentCapabilities::all().
        $this->app->singleton(IntentCapabilityRegistry::class, function () {
            $registry = new IntentCapabilityRegistry;

            foreach (DefaultIntentCapabilities::all() as $capability) {
                $registry->register($capability);
            }

            return $registry;
        });

        // Low-level resolver — used by DeterministicInterpretationProvider and RuleBasedCommandInterpreter
        $this->app->bind(IntentResolverInterface::class, RuleBasedIntentResolver::class);

        // Interpretation layer — provider is selected by config; deterministic is the default
        // and remains authoritative. 'ollama' is an opt-in sandbox; it only produces a
        // candidate NormalizedCommand and changes nothing about the proposal lifecycle.
        // An unknown value fails explicitly. No chain, manager, or hybrid mode.
        $this->app->bind(InterpretationProviderInterface::class, function ($app) {
            $provider = (string) $app['config']->get('actions.interpreter.provider', 'deterministic');

            return match ($provider) {
                'deterministic' => $app->make(DeterministicInterpretationProvider::class),
                'ollama' => new OllamaInterpretationProvider(
                    client: $app->make(LlmClientInterface::class),
                    validator: $app->make(LlmStructuredOutputValidator::class),
                    promptBuilder: $app->make(InterpretationPromptBuilder::class),
                    model: $app['config']->get('actions.llm.model'),
                ),
                default => throw new InvalidArgumentException(
                    "Unsupported Actions interpretation provider [{$provider}]."
                ),
            };
        });

        // Adapter bridges InterpretationProviderInterface → CommandInterpreterInterface
        // so ActionInterpreterService needs no changes when the provider is swapped.
        // NormalizedCommandValidator is auto-resolved (depends only on IntentRegistry singleton).
        $this->app->bind(CommandInterpreterInterface::class, InterpretationProviderAdapter::class);

        $this->app->bind(RefinementInterpreterInterface::class, RuleBasedRefinementInterpreter::class);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/actions')
            ->group(__DIR__.'/../../routes/api.php');

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'actions');
    }
}
