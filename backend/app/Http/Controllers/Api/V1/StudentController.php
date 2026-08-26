<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Services\Admissions\AdmissionApprovalService;
use App\Support\DailySeries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentController extends Controller
{
    public function __construct(private readonly AdmissionApprovalService $approvalService) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', 'in:pending,approved,rejected'],
        ]);

        $query = Student::with(['guardian', 'schoolClass'])->orderByDesc('created_at');

        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        return response()->json($query->get());
    }

    public function dailySummary(): JsonResponse
    {
        $days = 14;
        $rows = Student::selectRaw('DATE(created_at) as date, count(*) as count')
            ->where('created_at', '>=', Carbon::today()->subDays($days - 1))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get();

        $series = DailySeries::build($days, $rows, 'date', ['count' => 0], function (array $day, $row) {
            $day['count'] += (int) $row->count;

            return $day;
        });

        return response()->json($series);
    }

    public function show(Student $student): JsonResponse
    {
        return response()->json($student->load(['guardian', 'schoolClass']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'dob' => ['required', 'date'],
            'gender' => ['required', 'in:male,female'],
            'email' => ['required', 'email', 'max:255'],
            'contact' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'parent_id' => ['required', 'integer', 'exists:parents,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'is_transfer_student' => ['sometimes', 'boolean'],
            'photo' => [
                'nullable', 'file', 'mimes:'.implode(',', config('uploads.photo.mimes')),
                'max:'.config('uploads.photo.max_kb'),
            ],
            'transcript' => [
                'required_if:is_transfer_student,1', 'file', 'mimes:'.implode(',', config('uploads.document.mimes')),
                'max:'.config('uploads.document.max_kb'),
            ],
        ]);

        $data['is_transfer_student'] = $request->boolean('is_transfer_student');

        if ($data['is_transfer_student'] && ! $request->hasFile('transcript')) {
            return response()->json([
                'message' => 'A transcript is required for transfer students.',
                'errors' => ['transcript' => ['A transcript is required for transfer students.']],
            ], 422);
        }

        if ($request->hasFile('photo')) {
            $data['image_path'] = $request->file('photo')->store('student-photos', 'public');
        }

        if ($request->hasFile('transcript')) {
            $data['transcript_path'] = $request->file('transcript')->store('student-transcripts', 'local');
        }

        $data['status'] = 'pending';

        $student = Student::create($data);

        return response()->json($student->load(['guardian', 'schoolClass']), 201);
    }

    public function approve(Student $student): JsonResponse
    {
        try {
            $student = $this->approvalService->approve($student);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'A login already exists for this email address. Update the student\'s email before approving.',
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json($student->fresh()->load(['guardian', 'schoolClass']));
    }

    public function reject(Student $student): JsonResponse
    {
        abort_unless($student->status === 'pending', 422, 'Only pending applications can be rejected.');

        DB::transaction(function () use ($student) {
            $student->feeTransactions()->delete();
            $student->invoices()->delete();
            $this->deleteStudentFiles($student);
            $student->delete();
        });

        return response()->json(null, 204);
    }

    public function update(Request $request, Student $student): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:150'],
            'dob' => ['sometimes', 'date'],
            'gender' => ['sometimes', 'in:male,female'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($student->user_id)],
            'contact' => ['sometimes', 'nullable', 'string', 'max:20'],
            'address' => ['sometimes', 'nullable', 'string'],
            'class_id' => ['sometimes', 'nullable', 'integer', 'exists:classes,id'],
        ]);

        DB::transaction(function () use ($student, $data) {
            $student->update($data);

            // An approved student's email is also their login — keep the
            // two rows in lockstep, same as Staff::update syncing role.
            if (array_key_exists('email', $data) && $student->user_id) {
                User::whereKey($student->user_id)->update(['email' => $data['email']]);
            }
        });

        return response()->json($student->fresh()->load(['guardian', 'schoolClass']));
    }

    public function destroy(Student $student): JsonResponse
    {
        DB::transaction(function () use ($student) {
            // Unlike Staff::destroy (which deactivates to preserve payroll
            // history), deleting an admission fully removes the linked
            // login — an admission is provisional, and the email/username
            // it claimed needs to be free for the family to re-apply.
            // email_log.user_id is nullOnDelete, so that history survives.
            if ($student->user_id) {
                $user = User::find($student->user_id);
                if ($user) {
                    $user->tokens()->delete();
                    $user->delete();
                }
            }

            $this->deleteStudentFiles($student);

            // fee_transactions.student_id has no cascade rule, and deleting
            // an admission is a full teardown (same reasoning as the login
            // above) — any charge/payment history tied to it goes with it.
            $student->feeTransactions()->delete();
            $student->invoices()->delete();

            $student->delete();
        });

        return response()->json(null, 204);
    }

    private function deleteStudentFiles(Student $student): void
    {
        if ($student->image_path) {
            Storage::disk('public')->delete($student->image_path);
        }
        if ($student->transcript_path) {
            Storage::disk('local')->delete($student->transcript_path);
        }
        if ($student->admission_no) {
            Storage::disk('local')->delete("admission-letters/{$student->admission_no}.pdf");
        }
    }

    public function updateClass(Request $request, Student $student): JsonResponse
    {
        $data = $request->validate([
            'class_id' => ['required', 'integer', 'exists:classes,id'],
        ]);

        $student->update($data);

        return response()->json($student->fresh()->load(['guardian', 'schoolClass']));
    }

    public function downloadTranscript(Student $student): StreamedResponse
    {
        abort_unless($student->transcript_path, 404);

        return Storage::disk('local')->download($student->transcript_path, "{$student->full_name}-transcript.pdf");
    }

    public function downloadAdmissionLetter(Student $student): StreamedResponse
    {
        abort_unless($student->admission_no, 404);
        $path = "admission-letters/{$student->admission_no}.pdf";
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, "{$student->admission_no}-admission-letter.pdf");
    }
}
