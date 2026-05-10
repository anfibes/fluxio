<?php

namespace Fluxio\Actions\Providers;

use Fluxio\Actions\Contracts\CommandInterpreterInterface;
use Fluxio\Actions\Contracts\IntentResolverInterface;
use Fluxio\Actions\Contracts\RefinementInterpreterInterface;
use Fluxio\Actions\Interpreters\RuleBasedCommandInterpreter;
use Fluxio\Actions\Interpreters\RuleBasedRefinementInterpreter;
use Fluxio\Actions\Resolvers\RuleBasedIntentResolver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ActionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
