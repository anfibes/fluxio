<?php

use Fluxio\Leads\Http\Controllers\LeadController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/', [LeadController::class, 'index']);
    Route::post('/', [LeadController::class, 'store']);
    Route::get('/{lead}', [LeadController::class, 'show']);
    Route::patch('/{lead}', [LeadController::class, 'update']);
    Route::delete('/{lead}', [LeadController::class, 'destroy']);
});
