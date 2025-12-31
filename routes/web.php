<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;

Route::get('/', function () {
    return view('welcome');
});

// Broadcasting authentication route for WebSocket connections
// Note: CORS is handled by HandleCors middleware in bootstrap/app.php
// Using 'api' middleware for token-based authentication with Sanctum
Route::post('/broadcasting/auth', [\App\Http\Controllers\BroadcastAuthController::class, 'authenticate'])
    ->middleware(['api', 'auth:sanctum']);
Route::get('/broadcasting/auth', [\App\Http\Controllers\BroadcastAuthController::class, 'authenticate'])
    ->middleware(['api', 'auth:sanctum']);
