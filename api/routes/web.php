<?php

use App\Http\Controllers\Web\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [DashboardController::class, 'loginForm'])->name('login');
    Route::post('/login', [DashboardController::class, 'login'])->middleware('throttle:login');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [DashboardController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/history', [DashboardController::class, 'history'])->name('history');
    Route::post('/tunnels/{id}/disconnect', [DashboardController::class, 'disconnect'])->whereUuid('id')->name('tunnels.disconnect');
    Route::get('/tokens', [DashboardController::class, 'tokens'])->name('tokens');
    Route::post('/tokens', [DashboardController::class, 'createToken'])->name('tokens.create');
    Route::delete('/tokens/{id}', [DashboardController::class, 'revokeToken'])->whereNumber('id')->name('tokens.revoke');
    Route::get('/reservations', [DashboardController::class, 'reservations'])->name('reservations');
    Route::post('/reservations', [DashboardController::class, 'createReservation'])->name('reservations.create');
    Route::delete('/reservations/{id}', [DashboardController::class, 'deleteReservation'])->whereNumber('id')->name('reservations.delete');

    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('/', [DashboardController::class, 'adminOverview'])->name('admin');
        Route::post('/users', [DashboardController::class, 'adminCreateUser'])->name('admin.users.create');
        Route::patch('/users/{user}', [DashboardController::class, 'adminUpdateUser'])->name('admin.users.update');
        Route::post('/tunnels/{id}/disconnect', [DashboardController::class, 'adminDisconnect'])->whereUuid('id')->name('admin.tunnels.disconnect');
        Route::get('/audit', [DashboardController::class, 'adminAudit'])->name('admin.audit');
    });
});
