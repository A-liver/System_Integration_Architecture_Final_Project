<?php

use App\Http\Controllers\Api\GatewayController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api.gateway', 'throttle:120,1'])->group(function () {
    Route::get('/courses', [GatewayController::class, 'courses']);
    Route::get('/students/{studentNumber}', [GatewayController::class, 'student']);
    Route::post('/registrations', [GatewayController::class, 'register']);
    Route::get('/registrations/{registration}', [GatewayController::class, 'registration']);
    Route::post('/registrations/{registration}/payment', [GatewayController::class, 'payment']);
});
