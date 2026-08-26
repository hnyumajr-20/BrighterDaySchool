<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\FeeTransaction;
use App\Models\SalaryPayment;
use App\Models\Staff;
use Illuminate\Http\JsonResponse;

class FinanceController extends Controller
{
    public function overview(): JsonResponse
    {
        // amount_cents is positive for a charge, negative for a
        // payment/discount (PRD Section 3) — summing every row therefore
        // yields the net amount still owed across all students.
        $billed = (int) FeeTransaction::where('type', 'charge')->sum('amount_cents');
        $collected = (int) abs(FeeTransaction::where('type', 'payment')->sum('amount_cents'));
        $discounts = (int) abs(FeeTransaction::where('type', 'discount')->sum('amount_cents'));
        $adjustments = (int) FeeTransaction::where('type', 'adjustment')->sum('amount_cents');
        $salaryPaid = (int) SalaryPayment::sum('amount_cents');

        return response()->json([
            'fees' => [
                'total_billed_cents' => $billed,
                'total_collected_cents' => $collected,
                'total_discounts_cents' => $discounts,
                'outstanding_cents' => $billed - $collected - $discounts + $adjustments,
            ],
            'payroll' => [
                // Monthly obligation (what active staff are owed per month)
                // versus what's actually been paid out and what that leaves
                // available against fees collected — the same figures the
                // Accountant's own summary shows, so Admin sees it all too.
                'monthly_total_cents' => (int) Staff::where('status', 'active')->sum('salary_cents'),
                'active_staff_count' => Staff::where('status', 'active')->count(),
                'salary_paid_cents' => $salaryPaid,
                'available_cents' => $collected - $salaryPaid,
            ],
        ]);
    }
}
