<?php

use App\Http\Controllers\Api\V1\Admin\AcademicYearController;
use App\Http\Controllers\Api\V1\Admin\ClassController;
use App\Http\Controllers\Api\V1\Admin\PeriodController;
use App\Http\Controllers\Api\V1\Admin\SemesterController;
use App\Http\Controllers\Api\V1\Admin\StaffController;
use App\Http\Controllers\Api\V1\Admin\SubjectController;
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

        // Stub — dashboard reporting isn't in scope until its own phase;
        // this route only exists to prove RBAC middleware (Phase 0).
        Route::get('/reports/dashboard', fn () => response()->json(['message' => 'dashboard stub']))
            ->middleware('role:admin');

        // Stub — real Accountant module lands in Phase 4.
        Route::get('/fee-transactions', fn () => response()->json(['data' => []]))
            ->middleware('role:accountant');

        Route::middleware('role:admin')->group(function () {
            Route::post('/academic-years', [AcademicYearController::class, 'store']);
            Route::post('/semesters', [SemesterController::class, 'store']);
            Route::post('/periods', [PeriodController::class, 'store']);

            Route::get('/classes', [ClassController::class, 'index']);
            Route::post('/classes', [ClassController::class, 'store']);
            Route::put('/classes/{class}', [ClassController::class, 'update']);

            Route::get('/subjects', [SubjectController::class, 'index']);
            Route::post('/subjects', [SubjectController::class, 'store']);

            Route::post('/staff', [StaffController::class, 'store']);
            Route::get('/staff', [StaffController::class, 'index']);
            Route::get('/staff/{staff}', [StaffController::class, 'show']);
            Route::put('/staff/{staff}', [StaffController::class, 'update']);
            Route::delete('/staff/{staff}', [StaffController::class, 'destroy']);
        });
    });
});
