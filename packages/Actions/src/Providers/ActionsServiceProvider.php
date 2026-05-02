<?php

namespace Fluxio\Actions\Providers;

use Fluxio\Actions\Contracts\IntentResolverInterface;
use Fluxio\Actions\Resolvers\RuleBasedIntentResolver;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ActionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IntentResolverInterface::class, RuleBasedIntentResolver::class);
    }

    public function boot(): void
    {
        Route::middleware('api')
            ->prefix('api/actions')
            ->group(__DIR__ . '/../../routes/api.php');

        $this->loadTranslationsFrom(__DIR__ . '/../../lang', 'actions');
    }
}
