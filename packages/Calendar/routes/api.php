<?php

use Fluxio\Core\Http\Responses\ApiResponse;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => ApiResponse::success(['module' => 'calendar', 'status' => 'ok']));
