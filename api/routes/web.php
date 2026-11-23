<?php

use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\InstallController;
use App\Http\Controllers\Web\InstallScriptController;
use App\Http\Controllers\Web\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));
Route::get('/install.sh', [InstallScriptController::class, 'sh'])->middleware('throttle:web');
Route::get('/install.ps1', [InstallScriptController::class, 'ps1'])->middleware('throttle:web');
Route::get('/downloads/{file}', [InstallController::class, 'download'])->middleware('throttle:web')->where('file', '[A-Za-z0-9._-]+')->name('downloads');

Route::middleware(['throttle:web', 'guest'])->group(function () {
    Route::get('/login', [DashboardController::class, 'loginForm'])->name('login');
    Route::post('/login', [DashboardController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [DashboardController::class, 'registerForm'])->name('register');
    Route::post('/register', [DashboardController::class, 'register'])->middleware('throttle:register');
});

// Signed-in, but the email does not have to be verified yet.
Route::middleware(['throttle:web', 'auth', 'active'])->group(function () {
    Route::post('/logout', [DashboardController::class, 'logout'])->name('logout');
    Route::get('/email/verify', [DashboardController::class, 'verifyNotice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [DashboardController::class, 'verifyEmail'])->middleware('signed')->name('verification.verify');
    Route::post('/email/verification-notification', [DashboardController::class, 'resendVerification'])->middleware('throttle:verify')->name('verification.send');
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::patch('/profile', [ProfileController::class, 'update'])->middleware('throttle:login')->name('profile.update');
    Route::put('/profile/theme', [ProfileController::class, 'theme'])->name('profile.theme');
    Route::put('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:login')->name('profile.password');
});

Route::middleware(['throttle:web', 'auth', 'active', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('/install', [InstallController::class, 'show'])->name('install');
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
        Route::get('/tunnels', [DashboardController::class, 'adminTunnels'])->name('admin.tunnels');
        Route::post('/users', [DashboardController::class, 'adminCreateUser'])->name('admin.users.create');
        Route::patch('/users/{user}', [DashboardController::class, 'adminUpdateUser'])->name('admin.users.update');
        Route::post('/tunnels/{id}/disconnect', [DashboardController::class, 'adminDisconnect'])->whereUuid('id')->name('admin.tunnels.disconnect');
        Route::get('/audit', [DashboardController::class, 'adminAudit'])->name('admin.audit');
    });
});
