<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use App\Http\Controllers\Api\V1\Admin\AcademicYearController;
use App\Http\Controllers\Api\V1\Admin\ClassController;
use App\Http\Controllers\Api\V1\Admin\ClassSubjectController;
use App\Http\Controllers\Api\V1\Admin\FinanceController;
use App\Http\Controllers\Api\V1\Admin\PeriodController;
use App\Http\Controllers\Api\V1\Admin\SemesterController;
use App\Http\Controllers\Api\V1\Admin\StaffController;
use App\Http\Controllers\Api\V1\Admin\SubjectController;
use App\Http\Controllers\Api\V1\AcademicContextController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ClassFeeInstallmentController;
use App\Http\Controllers\Api\V1\FeeTransactionController;
use App\Http\Controllers\Api\V1\InvoiceController;
use App\Http\Controllers\Api\V1\ParentController;
use App\Http\Controllers\Api\V1\SalaryPaymentController;
use App\Http\Controllers\Api\V1\StudentController;
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

        // Available to every role — whatever academic year/semester/period
        // is currently open, so every dashboard can show what everything
        // else (grades, attendance, fees) is currently operating under.
        Route::get('/academic-years/current', [AcademicContextController::class, 'current']);

        // Stub — dashboard reporting isn't in scope until its own phase;
        // this route only exists to prove RBAC middleware (Phase 0).
        Route::get('/reports/dashboard', fn () => response()->json(['message' => 'dashboard stub']))
            ->middleware('role:admin');

        Route::middleware('role:admin')->group(function () {
            Route::get('/academic-years', [AcademicYearController::class, 'index']);
            Route::post('/academic-years', [AcademicYearController::class, 'store']);
            Route::put('/academic-years/{academicYear}', [AcademicYearController::class, 'update']);
            Route::get('/semesters', [SemesterController::class, 'index']);
            Route::post('/semesters', [SemesterController::class, 'store']);
            Route::put('/semesters/{semester}', [SemesterController::class, 'update']);
            Route::get('/periods', [PeriodController::class, 'index']);
            Route::post('/periods', [PeriodController::class, 'store']);
            Route::put('/periods/{period}', [PeriodController::class, 'update']);

            Route::post('/classes', [ClassController::class, 'store']);
            Route::put('/classes/{class}', [ClassController::class, 'update']);
            Route::delete('/classes/{class}', [ClassController::class, 'destroy']);

            Route::get('/classes/{class}/subjects', [ClassSubjectController::class, 'index']);
            Route::post('/classes/{class}/subjects', [ClassSubjectController::class, 'store']);
            Route::put('/classes/{class}/subjects/{classSubject}', [ClassSubjectController::class, 'update']);
            Route::delete('/classes/{class}/subjects/{classSubject}', [ClassSubjectController::class, 'destroy']);

            Route::get('/subjects', [SubjectController::class, 'index']);
            Route::post('/subjects', [SubjectController::class, 'store']);
            Route::put('/subjects/{subject}', [SubjectController::class, 'update']);
            Route::delete('/subjects/{subject}', [SubjectController::class, 'destroy']);

            Route::post('/staff', [StaffController::class, 'store']);
            Route::get('/staff/{staff}', [StaffController::class, 'show']);
            Route::get('/staff/{staff}/cv', [StaffController::class, 'downloadCv']);
            Route::put('/staff/{staff}', [StaffController::class, 'update']);
            Route::delete('/staff/{staff}', [StaffController::class, 'destroy']);

            Route::get('/finance/overview', [FinanceController::class, 'overview']);
        });

        Route::middleware('role:admin,registrar')->group(function () {
            Route::get('/parents', [ParentController::class, 'index']);
            Route::post('/parents', [ParentController::class, 'store']);

            Route::get('/admissions', [StudentController::class, 'index']);
            Route::get('/admissions/daily-summary', [StudentController::class, 'dailySummary']);
            Route::post('/students', [StudentController::class, 'store']);
            Route::put('/students/{student}', [StudentController::class, 'update']);
            Route::delete('/students/{student}', [StudentController::class, 'destroy']);
            Route::post('/students/{student}/approve', [StudentController::class, 'approve']);
            Route::post('/students/{student}/reject', [StudentController::class, 'reject']);
            Route::put('/students/{student}/class', [StudentController::class, 'updateClass']);
            Route::get('/students/{student}/transcript', [StudentController::class, 'downloadTranscript']);
            Route::get('/students/{student}/admission-letter', [StudentController::class, 'downloadAdmissionLetter']);

            Route::get('/attendance/staff', [AttendanceController::class, 'index']);
            Route::get('/attendance/staff/daily-summary', [AttendanceController::class, 'dailySummary']);
            Route::post('/attendance/staff/window/open', [AttendanceController::class, 'openWindow']);
            Route::post('/attendance/staff/mark', [AttendanceController::class, 'mark']);
        });

        Route::middleware('role:admin,registrar,accountant')->group(function () {
            // Read-only — the registrar needs this to assign students to a
            // class, and the accountant to see fee/installment context; only
            // admin may create/edit/delete classes (see admin-only group).
            Route::get('/classes', [ClassController::class, 'index']);

            // Read-only for registrar/accountant — write access to a
            // student's own record stays in the admin,registrar group above.
            Route::get('/students', [StudentController::class, 'index']);
            Route::get('/students/{student}', [StudentController::class, 'show']);
            Route::get('/students/{student}/balance', [FeeTransactionController::class, 'balance']);

            Route::get('/fee-transactions', [FeeTransactionController::class, 'index']);
            Route::get('/fee-transactions/students', [FeeTransactionController::class, 'studentsOverview']);
            Route::get('/fee-transactions/daily-collections', [FeeTransactionController::class, 'dailyCollections']);

            Route::get('/classes/{class}/fee-installments', [ClassFeeInstallmentController::class, 'index']);
            Route::get('/salary-payments', [SalaryPaymentController::class, 'index']);
            Route::get('/salary-payments/daily-summary', [SalaryPaymentController::class, 'dailySummary']);

            Route::get('/invoices', [InvoiceController::class, 'index']);
            Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
        });

        Route::middleware('role:admin,accountant')->group(function () {
            // The accountant needs the staff list to know who to pay.
            Route::get('/staff', [StaffController::class, 'index']);

            Route::get('/salary-payments/staff-overview', [SalaryPaymentController::class, 'staffOverview']);
            Route::get('/finance/accountant-summary', [SalaryPaymentController::class, 'summary']);
        });

        Route::middleware('role:accountant')->group(function () {
            // Admin is view-only on finance — only the accountant records
            // charges, payments, discounts, installment plans, salary
            // payments, and invoices/mobile-money confirmations.
            Route::post('/fee-transactions', [FeeTransactionController::class, 'store']);
            Route::post('/classes/{class}/fee-installments', [ClassFeeInstallmentController::class, 'store']);
            Route::post('/salary-payments', [SalaryPaymentController::class, 'store']);
            Route::post('/invoices', [InvoiceController::class, 'store']);
            Route::post('/invoices/{invoice}/pay', [InvoiceController::class, 'pay']);
        });
    });
});
