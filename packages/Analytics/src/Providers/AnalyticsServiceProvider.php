<?php

namespace Fluxio\Analytics\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class AnalyticsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Routes del modulo
        $routes = __DIR__ . '/../../routes/api.php';
        if (file_exists($routes)) {
            Route::middleware('api')
                ->prefix('api/analytics')
                ->group($routes);
        }

        // Migrazioni del modulo
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }
}
