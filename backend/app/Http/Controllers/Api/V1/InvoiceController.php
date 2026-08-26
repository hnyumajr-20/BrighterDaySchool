<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FeeTransaction;
use App\Models\Invoice;
use App\Models\Student;
use App\Services\Admissions\AdmissionApprovalService;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceController extends Controller
{
    private const RELATIONS = ['student:id,full_name,admission_no,status', 'createdBy:id,full_name', 'confirmedBy:id,full_name'];

    public function __construct(private readonly AdmissionApprovalService $approvalService) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['sometimes', 'integer', 'exists:students,id'],
            'status' => ['sometimes', 'in:unpaid,paid,cancelled'],
            'type' => ['sometimes', 'in:registration,tuition,other'],
            'payment_method' => ['sometimes', 'in:cash,orange_money,lonestar_mtn'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        $query = Invoice::with(self::RELATIONS)->orderByDesc('created_at');

        if (isset($data['student_id'])) {
            $query->where('student_id', $data['student_id']);
        }
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (isset($data['type'])) {
            $query->where('type', $data['type']);
        }
        if (isset($data['payment_method'])) {
            $query->where('payment_method', $data['payment_method']);
        }
        if (isset($data['from'])) {
            $query->where('paid_at', '>=', $data['from']);
        }
        if (isset($data['to'])) {
            $query->where('paid_at', '<=', $data['to']);
        }

        return response()->json($query->get());
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json($invoice->load(self::RELATIONS));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'type' => ['required', 'in:registration,tuition,other'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string'],
        ]);

        $student = Student::findOrFail($data['student_id']);

        if ($data['type'] === 'registration') {
            abort_unless($student->status === 'pending', 422, 'Registration invoices are only for pending applicants.');
            abort_if(
                Invoice::where('student_id', $student->id)->where('type', 'registration')->where('status', 'unpaid')->exists(),
                422,
                'This student already has an unpaid registration invoice.',
            );
        } else {
            abort_unless($student->status === 'approved', 422, 'Only approved students can be invoiced for this.');
        }

        $activeYear = AcademicYear::where('status', 'active')->first();
        abort_unless($activeYear, 422, 'No active academic year — set one before creating invoices.');

        $invoice = DB::transaction(function () use ($data, $student, $activeYear, $request) {
            $staffId = $request->user()->staff?->id;

            $invoice = Invoice::create([
                'invoice_no' => $this->generateUniqueInvoiceNo(),
                'student_id' => $student->id,
                'type' => $data['type'],
                'amount_cents' => $data['amount_cents'],
                'note' => $data['note'] ?? null,
                'status' => 'unpaid',
                'created_by' => $staffId,
                'academic_year_id' => $activeYear->id,
            ]);

            FeeTransaction::create([
                'student_id' => $student->id,
                'amount_cents' => $data['amount_cents'],
                'type' => 'charge',
                'invoice_id' => $invoice->id,
                'note' => "Invoice {$invoice->invoice_no} ({$data['type']})",
                'recorded_by' => $staffId,
                'academic_year_id' => $activeYear->id,
            ]);

            return $invoice;
        });

        return response()->json($invoice->load(self::RELATIONS), 201);
    }

    public function pay(Request $request, Invoice $invoice, PaymentGatewayInterface $gateway): JsonResponse
    {
        abort_unless($invoice->status === 'unpaid', 422, 'This invoice has already been paid or is cancelled.');

        $data = $request->validate([
            'payment_method' => ['required', 'in:cash,orange_money,lonestar_mtn'],
            'payer_phone' => ['required_if:payment_method,orange_money,lonestar_mtn', 'string', 'regex:/^[0-9]{8,10}$/'],
        ]);

        $activeYear = AcademicYear::where('status', 'active')->first();
        abort_unless($activeYear, 422, 'No active academic year — set one before recording payments.');

        $gatewayTransactionId = null;
        if ($data['payment_method'] !== 'cash') {
            $result = $gateway->charge($data['payment_method'], $data['payer_phone'], $invoice->amount_cents, $invoice->invoice_no);
            abort_unless($result->successful, 422, $result->message ?? 'Payment failed.');
            $gatewayTransactionId = $result->gatewayTransactionId;
        }

        $invoice = DB::transaction(function () use ($invoice, $data, $gatewayTransactionId, $activeYear, $request) {
            $staffId = $request->user()->staff?->id;

            $invoice->update([
                'status' => 'paid',
                'payment_method' => $data['payment_method'],
                'gateway_transaction_id' => $gatewayTransactionId,
                'payer_phone' => $data['payer_phone'] ?? null,
                'confirmed_by' => $staffId,
                'paid_at' => now(),
            ]);

            FeeTransaction::create([
                'student_id' => $invoice->student_id,
                'amount_cents' => -$invoice->amount_cents,
                'type' => 'payment',
                'invoice_id' => $invoice->id,
                'note' => "Payment for invoice {$invoice->invoice_no}",
                'recorded_by' => $staffId,
                'academic_year_id' => $activeYear->id,
            ]);

            return $invoice->fresh();
        });

        $autoApproved = false;
        $autoApprovalError = null;
        $student = $invoice->student;

        if ($invoice->type === 'registration' && $student->status === 'pending') {
            try {
                $this->approvalService->approve($student);
                $autoApproved = true;
            } catch (ValidationException $e) {
                $autoApprovalError = collect($e->errors())->flatten()->first() ?? $e->getMessage();
            }
        }

        return response()->json(array_merge(
            $invoice->load(self::RELATIONS)->toArray(),
            ['admission_auto_approved' => $autoApproved, 'admission_auto_approval_error' => $autoApprovalError],
        ));
    }

    private function generateUniqueInvoiceNo(): string
    {
        do {
            $candidate = 'INV-'.now()->year.'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Invoice::where('invoice_no', $candidate)->exists());

        return $candidate;
    }
}
