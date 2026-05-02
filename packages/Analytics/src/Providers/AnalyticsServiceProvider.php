<?php

namespace Fluxio\Analytics\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AnalyticsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $routes = __DIR__.'/../../routes/api.php';
        if (file_exists($routes)) {
            Route::middleware('api')
                ->prefix('api/analytics')
                ->group($routes);
        }

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
