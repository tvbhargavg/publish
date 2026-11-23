<?php

// Tunnel-server-only endpoints (X-Internal-Secret). Never expose publicly.
use App\Http\Controllers\Internal\InternalController;
use Illuminate\Support\Facades\Route;

Route::post('auth/validate', [InternalController::class, 'validateToken']);
Route::post('tunnels/authorize', [InternalController::class, 'authorizeTunnel']);
Route::post('tunnels/{id}/closed', [InternalController::class, 'closed']);
