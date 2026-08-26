<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\ClassFeeInstallment;
use App\Models\FeeTransaction;
use App\Models\Student;
use Illuminate\Support\Carbon;

class ClassFeeInstallmentChargeService
{
    /**
     * For every installment whose due date has arrived, charge any
     * approved student in that class who hasn't been charged it yet.
     * Idempotent by design (the whereDoesntHave re-check each run) — no
     * "already processed" flag needed, and it keeps enforcing after the
     * due date for anyone newly assigned to the class later.
     */
    public function run(): void
    {
        $activeYear = AcademicYear::where('status', 'active')->first();
        if (! $activeYear) {
            // Same defensive skip as the approval-time auto-charge — a
            // finance-config gap shouldn't crash the scheduler.
            return;
        }

        ClassFeeInstallment::whereNotNull('due_date')
            ->where('due_date', '<=', Carbon::today())
            ->get()
            ->each(function (ClassFeeInstallment $installment) use ($activeYear) {
                Student::where('status', 'approved')
                    ->where('class_id', $installment->class_id)
                    ->whereDoesntHave('feeTransactions', fn ($query) => $query->where('class_fee_installment_id', $installment->id))
                    ->get()
                    ->each(fn (Student $student) => FeeTransaction::create([
                        'student_id' => $student->id,
                        'amount_cents' => $installment->amount_cents,
                        'type' => 'charge',
                        'class_fee_installment_id' => $installment->id,
                        'note' => "Installment {$installment->sequence} of 3 — auto-charged on due date.",
                        'academic_year_id' => $activeYear->id,
                    ]));
            });
    }
}
