<?php

use Fluxio\Actions\Http\Controllers\ActionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/interpret', [ActionController::class, 'interpret']);
    Route::post('/{proposal}/confirm', [ActionController::class, 'confirm']);
});
