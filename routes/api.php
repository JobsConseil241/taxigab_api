<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\RideController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Taxi Gab API Routes
|--------------------------------------------------------------------------
*/

// Health check
Route::get('/health', fn () => ['status' => 'ok', 'app' => config('app.name')]);

// ------------------------------------------------------------------ Auth
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login',    [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me',      [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// ------------------------------------------------------------------ Authenticated routes
Route::middleware('auth:sanctum')->group(function () {

    // ---------------- Passager : rides ----------------
    Route::prefix('rides')->group(function () {
        Route::post('/estimate', [RideController::class, 'estimate']);
        Route::get('/history',   [RideController::class, 'history']);
        Route::post('/',         [RideController::class, 'store']);
        Route::get('/{ride}',    [RideController::class, 'show'])->whereNumber('ride');
        Route::post('/{ride}/cancel', [RideController::class, 'cancel'])->whereNumber('ride');
        Route::post('/{ride}/rate',   [RideController::class, 'rate'])->whereNumber('ride');
    });

    // ---------------- Chauffeur ----------------
    Route::prefix('driver')->group(function () {
        Route::post('/toggle-online', [DriverController::class, 'toggleOnline']);
        Route::post('/location',      [LocationController::class, 'update']);
        Route::get('/available-rides', [DriverController::class, 'availableRides']);
        Route::get('/stats',           [DriverController::class, 'stats']);

        Route::post('/rides/{ride}/accept',   [DriverController::class, 'accept'])->whereNumber('ride');
        Route::post('/rides/{ride}/arrive',   [DriverController::class, 'arrive'])->whereNumber('ride');
        Route::post('/rides/{ride}/start',    [DriverController::class, 'start'])->whereNumber('ride');
        Route::post('/rides/{ride}/complete', [DriverController::class, 'complete'])->whereNumber('ride');
    });

    // ---------------- Admin ----------------
    Route::prefix('admin')->group(function () {
        Route::get('/dashboard',  [AdminController::class, 'dashboard']);
        Route::get('/rides',      [AdminController::class, 'rides']);
        Route::get('/drivers',    [AdminController::class, 'drivers']);
        Route::get('/drivers/locations', [AdminController::class, 'driverLocations']);
        Route::get('/users',      [AdminController::class, 'users']);
        Route::patch('/drivers/{driver}/approve', [AdminController::class, 'approveDriver']);

        Route::get('/formulas',            [AdminController::class, 'formulas']);
        Route::post('/formulas',           [AdminController::class, 'storeFormula']);
        Route::put('/formulas/{formula}',  [AdminController::class, 'updateFormula']);
        Route::delete('/formulas/{formula}', [AdminController::class, 'destroyFormula']);
    });
});
