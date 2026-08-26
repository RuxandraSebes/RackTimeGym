<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DoorCheckInController;
use App\Http\Controllers\GymAccountController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('role:member')->group(function () {
        Route::post('/checkins/door/{token}', [DoorCheckInController::class, 'store']);
    });

    Route::middleware('role:staff,owner')->group(function () {
        Route::get('/gym/users', [GymAccountController::class, 'index']);
        Route::post('/gym/users', [GymAccountController::class, 'store']);
        Route::get('/gym/door-qr', [DoorCheckInController::class, 'show']);
        Route::post('/gym/checkins', [DoorCheckInController::class, 'storeManual']);
    });
});
