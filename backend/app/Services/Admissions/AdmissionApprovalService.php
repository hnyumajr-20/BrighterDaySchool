<?php

namespace App\Services\Admissions;

use App\Jobs\SendLoggedEmailJob;
use App\Models\AcademicYear;
use App\Models\EmailLog;
use App\Models\FeeTransaction;
use App\Models\Student;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The side effects of admission approval — generating a login, an admission
 * number, the PDF letter, and queuing the welcome email — live here so both
 * the manual admin/registrar "Approve" action and the automatic trigger
 * fired once a registration-fee invoice is paid produce identical results.
 */
class AdmissionApprovalService
{
    /**
     * @throws ValidationException if a login already exists for this student's email
     */
    public function approve(Student $student): Student
    {
        abort_unless($student->status === 'pending', 422, 'Only pending applications can be approved.');

        if (User::where('email', $student->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['A login already exists for this email address.'],
            ]);
        }

        $temporaryPassword = Str::password(12);

        return DB::transaction(function () use ($student, $temporaryPassword) {
            $admissionNo = $this->generateUniqueAdmissionNo();

            $user = User::create([
                'role' => 'student',
                'username' => $admissionNo,
                'email' => $student->email,
                'password_hash' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'status' => 'active',
            ]);

            $student->update([
                'status' => 'approved',
                'admission_no' => $admissionNo,
                'user_id' => $user->id,
            ]);
            $student->load(['guardian', 'schoolClass']);
            $this->autoChargeClassFee($student);

            $letterPath = $this->generateAdmissionLetter($student, $admissionNo, $temporaryPassword);

            $emailLog = EmailLog::create([
                'user_id' => $user->id,
                'type' => 'admission_letter',
                'status' => 'queued',
            ]);

            SendLoggedEmailJob::dispatch(
                $emailLog->id,
                $user->email,
                'Welcome to '.config('school.name').' — your admission is confirmed',
                "Congratulations! {$student->full_name}'s admission to ".config('school.name')." has been approved.\n\n".
                "Admission number / username: {$admissionNo}\nTemporary password: {$temporaryPassword}\n\n".
                "Sign in and you will be asked to set a new password before you can access the dashboard.\n\n".
                'Your full admission letter is attached to this email.',
                $letterPath,
                "{$admissionNo}-admission-letter.pdf",
            );

            return $student;
        });
    }

    private function autoChargeClassFee(Student $student): void
    {
        if (! $student->schoolClass || $student->schoolClass->fee_amount_cents <= 0) {
            return;
        }

        $activeYear = AcademicYear::where('status', 'active')->first();
        if (! $activeYear) {
            // A finance-config gap shouldn't block the student from getting
            // their login — the accountant can charge this by hand later.
            return;
        }

        // A class with a full 3-installment plan is charged one installment
        // at a time — approval only bills installment 1, and the accountant
        // charges 2 and 3 by hand when they come due.
        $installments = $student->schoolClass->classFeeInstallments()->orderBy('sequence')->get();
        $firstInstallment = $installments->count() === 3 ? $installments->firstWhere('sequence', 1) : null;

        FeeTransaction::create([
            'student_id' => $student->id,
            'amount_cents' => $firstInstallment ? $firstInstallment->amount_cents : $student->schoolClass->fee_amount_cents,
            'type' => 'charge',
            'class_fee_installment_id' => $firstInstallment?->id,
            'note' => $firstInstallment
                ? 'Installment 1 of 3 — auto-charged on admission approval.'
                : 'Class fee — auto-charged on admission approval.',
            'academic_year_id' => $activeYear->id,
        ]);
    }

    private function generateAdmissionLetter(Student $student, string $admissionNo, string $temporaryPassword): string
    {
        $logoPath = resource_path('images/logo.png');

        $pdf = Pdf::loadView('pdf.admission-letter', [
            'student' => $student,
            'admissionNo' => $admissionNo,
            'temporaryPassword' => $temporaryPassword,
            'issuedAt' => now()->format('F j, Y'),
            'loginUrl' => config('school.login_url'),
            'logoBase64' => file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null,
            'school' => [
                'name' => config('school.name'),
                'address' => config('school.address'),
                'phone' => config('school.phone'),
                'email' => config('school.email'),
            ],
        ])->setPaper('a4');

        $path = "admission-letters/{$admissionNo}.pdf";
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }

    private function generateUniqueAdmissionNo(): string
    {
        do {
            $candidate = 'BDS-'.now()->year.'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (User::where('username', $candidate)->exists() || Student::where('admission_no', $candidate)->exists());

        return $candidate;
    }
}
