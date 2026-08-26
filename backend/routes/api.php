<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\DoorCheckInController;
use App\Http\Controllers\EquipmentCheckInController;
use App\Http\Controllers\EquipmentUnitController;
use App\Http\Controllers\GymAccountController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OccupancyController;
use App\Http\Controllers\OccupancyHeatmapController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\WaitlistEntryController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::get('/gym/occupancy', [OccupancyController::class, 'show']);
    Route::get('/gym/occupancy/heatmap', [OccupancyHeatmapController::class, 'show']);
    Route::get('/classes', [ClassController::class, 'index']);
    Route::get('/equipment-units', [EquipmentUnitController::class, 'index']);

    Route::middleware('role:member')->group(function () {
        Route::post('/checkins/door/{token}', [DoorCheckInController::class, 'store']);
        Route::post('/checkins/equipment/{token}', [EquipmentCheckInController::class, 'store']);
        Route::get('/bookings', [BookingController::class, 'index']);
        Route::post('/classes/{class}/bookings', [BookingController::class, 'store']);
        Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);
        Route::get('/waitlist-entries', [WaitlistEntryController::class, 'index']);
        Route::post('/waitlist-entries/{waitlistEntry}/confirm', [WaitlistEntryController::class, 'confirm']);
        Route::get('/reservations', [ReservationController::class, 'index']);
        Route::post('/equipment-units/{equipmentUnit}/reservations', [ReservationController::class, 'store']);
    });

    Route::middleware('role:staff,owner')->group(function () {
        Route::get('/gym/users', [GymAccountController::class, 'index']);
        Route::post('/gym/users', [GymAccountController::class, 'store']);
        Route::get('/gym/door-qr', [DoorCheckInController::class, 'show']);
        Route::post('/gym/checkins', [DoorCheckInController::class, 'storeManual']);
        Route::post('/classes', [ClassController::class, 'store']);
        Route::patch('/classes/{class}', [ClassController::class, 'update']);
        Route::post('/classes/{class}/cancel', [ClassController::class, 'cancel']);
        Route::get('/classes/{class}/qr', [ClassController::class, 'showQr']);
        Route::get('/classes/{class}/bookings', [BookingController::class, 'roster']);
        Route::post('/equipment-units', [EquipmentUnitController::class, 'store']);
        Route::get('/equipment-units/{equipmentUnit}/qr', [EquipmentUnitController::class, 'showQr']);
    });
});
