<?php

namespace Fluxio\Calendar\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class CalendarServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $routes = __DIR__ . '/../../routes/api.php';
        if (file_exists($routes)) {
            Route::middleware('api')
                ->prefix('api/calendar')
                ->group($routes);
        }

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }
}
