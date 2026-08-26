<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\FeeTransaction;
use App\Models\SalaryPayment;
use App\Models\Staff;
use App\Support\DailySeries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalaryPaymentController extends Controller
{
    public function dailySummary(): JsonResponse
    {
        $days = 14;
        $rows = SalaryPayment::selectRaw('DATE(created_at) as date, sum(amount_cents) as total')
            ->where('created_at', '>=', Carbon::today()->subDays($days - 1))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get();

        $series = DailySeries::build($days, $rows, 'date', ['paid_cents' => 0], function (array $day, $row) {
            $day['paid_cents'] += (int) $row->total;

            return $day;
        });

        return response()->json($series);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'staff_id' => ['sometimes', 'integer', 'exists:staff,id'],
        ]);

        $query = SalaryPayment::with('recordedBy:id,full_name')->orderByDesc('created_at')->orderByDesc('id');

        if (isset($data['staff_id'])) {
            $query->where('staff_id', $data['staff_id']);
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string'],
        ]);

        $staff = Staff::findOrFail($data['staff_id']);
        abort_unless($staff->status === 'active', 422, 'Only active staff can be paid.');

        // A staff member can be paid in several partial installments, but
        // the running total for the current calendar month can never pass
        // their own assigned salary — a hard cap, unlike the informational
        // "available balance" figure in summary()/staffOverview() below.
        $remaining = $staff->salary_cents - $this->paidThisMonth($staff->id);
        abort_if($remaining <= 0, 422, 'This staff member has already received their full salary for this month.');
        abort_if(
            $data['amount_cents'] > $remaining,
            422,
            'This payment would exceed their remaining salary for this month ($'.number_format($remaining / 100, 2).' left).',
        );

        $payment = SalaryPayment::create([
            'staff_id' => $data['staff_id'],
            'amount_cents' => $data['amount_cents'],
            'note' => $data['note'] ?? null,
            'recorded_by' => $request->user()->staff?->id,
        ]);

        return response()->json($payment->load('recordedBy:id,full_name'), 201);
    }

    public function staffOverview(): JsonResponse
    {
        $staff = Staff::where('status', 'active')->orderBy('full_name')->get()
            ->map(function (Staff $member) {
                $paidThisMonth = $this->paidThisMonth($member->id);

                return [
                    'id' => $member->id,
                    'full_name' => $member->full_name,
                    'staff_role' => $member->staff_role,
                    'photo_url' => $member->photo_url,
                    'salary_cents' => $member->salary_cents,
                    'paid_this_month_cents' => $paidThisMonth,
                    'remaining_this_month_cents' => max(0, $member->salary_cents - $paidThisMonth),
                ];
            });

        return response()->json($staff);
    }

    private function paidThisMonth(int $staffId): int
    {
        return (int) SalaryPayment::where('staff_id', $staffId)
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('amount_cents');
    }

    public function summary(): JsonResponse
    {
        $collected = (int) abs(FeeTransaction::where('type', 'payment')->sum('amount_cents'));
        $billed = (int) FeeTransaction::where('type', 'charge')->sum('amount_cents');
        $discounts = (int) abs(FeeTransaction::where('type', 'discount')->sum('amount_cents'));
        $salaryPaid = (int) SalaryPayment::sum('amount_cents');

        return response()->json([
            'fees_collected_cents' => $collected,
            'outstanding_cents' => $billed - $collected - $discounts,
            'salary_paid_cents' => $salaryPaid,
            'available_cents' => $collected - $salaryPaid,
        ]);
    }
}
