<?php

namespace Fluxio\Leads\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class LeadsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $routes = __DIR__.'/../../routes/api.php';
        if (file_exists($routes)) {
            Route::middleware('api')
                ->prefix('api/leads')
                ->group($routes);
        }

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'leads');
    }
}
