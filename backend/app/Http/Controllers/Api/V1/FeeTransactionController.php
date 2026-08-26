<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\ClassFeeInstallment;
use App\Models\FeeTransaction;
use App\Models\Student;
use App\Support\DailySeries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class FeeTransactionController extends Controller
{
    public function dailyCollections(): JsonResponse
    {
        $days = 14;
        $rows = FeeTransaction::selectRaw('DATE(created_at) as date, sum(amount_cents) as total')
            ->where('type', 'payment')
            ->where('created_at', '>=', Carbon::today()->subDays($days - 1))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get();

        $series = DailySeries::build($days, $rows, 'date', ['collected_cents' => 0], function (array $day, $row) {
            // Payments are stored negative — flip to a positive "money in" figure.
            $day['collected_cents'] += (int) abs($row->total);

            return $day;
        });

        return response()->json($series);
    }

    public function studentsOverview(): JsonResponse
    {
        $students = Student::where('status', 'approved')
            ->with('schoolClass')
            ->withSum('feeTransactions as balance_cents', 'amount_cents')
            ->orderBy('full_name')
            ->get()
            ->map(fn (Student $student) => [
                'id' => $student->id,
                'full_name' => $student->full_name,
                'admission_no' => $student->admission_no,
                'photo_url' => $student->photo_url,
                'school_class' => $student->schoolClass,
                'balance_cents' => (int) ($student->balance_cents ?? 0),
            ]);

        return response()->json($students);
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
        ]);

        $transactions = FeeTransaction::where('student_id', $data['student_id'])
            ->with('recordedBy:id,full_name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json($transactions);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'in:charge,payment,discount'],
            'class_fee_installment_id' => ['nullable', 'integer', 'exists:class_fee_installments,id'],
            'note' => ['nullable', 'string'],
        ]);

        $student = Student::findOrFail($data['student_id']);
        abort_unless($student->status === 'approved', 422, 'Only approved students have a fee account.');

        $activeYear = AcademicYear::where('status', 'active')->first();
        abort_unless($activeYear, 422, 'No active academic year — set one before recording fees.');

        $installmentId = $data['class_fee_installment_id'] ?? null;
        if ($installmentId) {
            $installment = ClassFeeInstallment::findOrFail($installmentId);
            abort_unless($data['type'] === 'charge', 422, 'Installments can only be tagged on a charge.');
            abort_unless($installment->class_id === $student->class_id, 422, "That installment doesn't belong to this student's class.");
            abort_if(
                FeeTransaction::where('student_id', $student->id)->where('class_fee_installment_id', $installmentId)->exists(),
                422,
                'This installment has already been charged for this student.',
            );
        }

        $transaction = FeeTransaction::create([
            'student_id' => $data['student_id'],
            // The client always sends a positive amount — a charge adds to
            // the balance, a payment or discount reduces it (PRD Section 3).
            'amount_cents' => $data['type'] === 'charge' ? $data['amount_cents'] : -$data['amount_cents'],
            'type' => $data['type'],
            'class_fee_installment_id' => $installmentId,
            'note' => $data['note'] ?? null,
            'recorded_by' => $request->user()->staff?->id,
            'academic_year_id' => $activeYear->id,
        ]);

        return response()->json($transaction->load('recordedBy:id,full_name'), 201);
    }

    public function balance(Student $student): JsonResponse
    {
        return response()->json([
            'balance_cents' => (int) $student->feeTransactions()->sum('amount_cents'),
        ]);
    }
}
