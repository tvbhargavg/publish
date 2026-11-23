<?php

use App\Http\Controllers\Api\Admin\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Controllers\Api\TunnelController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('config', [ConfigController::class, 'show'])->middleware('throttle:api');
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware(['auth:sanctum', 'active', 'verified', 'throttle:api'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::get('tokens', [TokenController::class, 'index']);
        Route::post('tokens', [TokenController::class, 'store']);
        Route::delete('tokens/{id}', [TokenController::class, 'destroy'])->whereNumber('id');

        Route::get('reservations', [ReservationController::class, 'index']);
        Route::post('reservations', [ReservationController::class, 'store']);
        Route::delete('reservations/{id}', [ReservationController::class, 'destroy'])->whereNumber('id');

        Route::get('tunnels', [TunnelController::class, 'index']);
        Route::get('tunnels/history', [TunnelController::class, 'history']);
        Route::delete('tunnels/{id}', [TunnelController::class, 'destroy'])->whereUuid('id');
        Route::get('usage', [TunnelController::class, 'usage']);

        Route::middleware('admin')->prefix('admin')->group(function () {
            Route::get('users', [AdminController::class, 'users']);
            Route::post('users', [AdminController::class, 'createUser']);
            Route::patch('users/{user}', [AdminController::class, 'updateUser']);
            Route::get('tunnels', [AdminController::class, 'tunnels']);
            Route::delete('tunnels/{id}', [AdminController::class, 'disconnect'])->whereUuid('id');
            Route::get('audit-logs', [AdminController::class, 'audit']);
        });
    });
});
