<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

        Route::get('/user', fn (Request $request) => $request->user());

        // Stub routes — prove RBAC middleware works end to end (Phase 0
        // acceptance criteria). Real implementations land in Phase 1 (Admin)
        // and Phase 4 (Accountant).
        Route::get('/reports/dashboard', fn () => response()->json(['message' => 'dashboard stub']))
            ->middleware('role:admin');

        Route::get('/fee-transactions', fn () => response()->json(['data' => []]))
            ->middleware('role:accountant');
    });
});
