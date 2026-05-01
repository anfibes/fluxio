<?php
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn() => response()->json([
  'module' => basename(dirname(__DIR__)),
  'status' => 'ok'
]));
