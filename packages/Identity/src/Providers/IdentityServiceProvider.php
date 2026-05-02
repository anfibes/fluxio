<?php

namespace Fluxio\Identity\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $routes = __DIR__.'/../../routes/api.php';
        if (file_exists($routes)) {
            Route::middleware('api')
                ->prefix('api/auth')
                ->group($routes);
        }

        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'identity');
    }
}
