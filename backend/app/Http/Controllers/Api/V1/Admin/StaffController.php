<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendLoggedEmailJob;
use App\Models\EmailLog;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StaffController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Staff::orderBy('full_name')->get());
    }

    public function show(Staff $staff): JsonResponse
    {
        return response()->json($staff);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:150'],
            'dob' => ['required', 'date'],
            'gender' => ['nullable', 'in:male,female'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'contact' => ['required', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'staff_role' => ['required', 'in:registrar,accountant,teacher,librarian'],
            'salary_cents' => ['required', 'integer', 'min:0'],
            'rfid_uid' => ['nullable', 'string', 'max:64', 'unique:staff,rfid_uid'],
            'photo' => [
                'required', 'file', 'mimes:'.implode(',', config('uploads.photo.mimes')),
                'max:'.config('uploads.photo.max_kb'),
            ],
            'cv' => [
                'required', 'file', 'mimes:'.implode(',', config('uploads.document.mimes')),
                'max:'.config('uploads.document.max_kb'),
            ],
        ]);

        $imagePath = $request->file('photo')->store('staff-photos', 'public');
        $cvPath = $request->file('cv')->store('staff-cvs', 'local');

        $temporaryPassword = Str::password(12);

        $staff = DB::transaction(function () use ($data, $temporaryPassword, $imagePath, $cvPath) {
            $user = User::create([
                'role' => $data['staff_role'],
                'username' => $this->generateUniqueUsername(),
                'email' => $data['email'],
                'password_hash' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'status' => 'active',
            ]);

            $staff = Staff::create([
                'user_id' => $user->id,
                'staff_no' => $this->generateUniqueStaffNo(),
                'full_name' => $data['full_name'],
                'dob' => $data['dob'],
                'gender' => $data['gender'] ?? null,
                'email' => $data['email'],
                'contact' => $data['contact'],
                'address' => $data['address'] ?? null,
                'staff_role' => $data['staff_role'],
                'salary_cents' => $data['salary_cents'],
                'rfid_uid' => $data['rfid_uid'] ?? null,
                'image_path' => $imagePath,
                'cv_path' => $cvPath,
                'status' => 'active',
            ]);

            $emailLog = EmailLog::create([
                'user_id' => $user->id,
                'type' => 'staff_credentials',
                'status' => 'queued',
            ]);

            SendLoggedEmailJob::dispatch(
                $emailLog->id,
                $user->email,
                'Your Brighter Day SMIS account',
                "An account has been created for you.\nUsername: {$user->username}\nTemporary password: {$temporaryPassword}\n\nYou will be asked to set a new password on first login.",
            );

            return $staff;
        });

        return response()->json($staff->refresh(), 201);
    }

    public function update(Request $request, Staff $staff): JsonResponse
    {
        $data = $request->validate([
            'full_name' => ['sometimes', 'string', 'max:150'],
            'dob' => ['sometimes', 'nullable', 'date'],
            'gender' => ['sometimes', 'nullable', 'in:male,female'],
            'contact' => ['sometimes', 'nullable', 'string', 'max:20'],
            'address' => ['sometimes', 'nullable', 'string'],
            'staff_role' => ['sometimes', 'in:registrar,accountant,teacher,librarian'],
            'salary_cents' => ['sometimes', 'integer', 'min:0'],
            'rfid_uid' => ['sometimes', 'nullable', 'string', 'max:64', 'unique:staff,rfid_uid,'.$staff->id],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        DB::transaction(function () use ($staff, $data) {
            $staff->update($data);

            // The staff record and its login are two rows (staff.status,
            // users.status) — keep them in lockstep so "inactive" actually
            // blocks sign-in, not just hides the row from lists.
            if (array_key_exists('status', $data) && $staff->user_id) {
                $this->syncUserAccessToStatus($staff->user_id, $data['status']);
            }

            // The role lives on both rows too (users.role drives RBAC);
            // keep them matched so a role change actually changes access.
            if (array_key_exists('staff_role', $data) && $staff->user_id) {
                User::whereKey($staff->user_id)->update(['role' => $data['staff_role']]);
            }
        });

        return response()->json($staff->fresh());
    }

    public function downloadCv(Staff $staff): StreamedResponse
    {
        abort_unless($staff->cv_path, 404);

        return Storage::disk('local')->download($staff->cv_path, "{$staff->staff_no}-cv.pdf");
    }

    public function destroy(Staff $staff): JsonResponse
    {
        DB::transaction(function () use ($staff) {
            // Deactivating (rather than deleting) the linked user preserves
            // email_log/password_resets history tied to that account while
            // still cutting off access immediately.
            if ($staff->user_id) {
                $this->syncUserAccessToStatus($staff->user_id, 'inactive');
            }

            $staff->delete();
        });

        return response()->json(null, 204);
    }

    private function syncUserAccessToStatus(int $userId, string $status): void
    {
        $user = User::find($userId);
        if (! $user) {
            return;
        }

        $user->update(['status' => $status]);

        if ($status === 'inactive') {
            $user->tokens()->delete();
        }
    }

    private function generateUniqueUsername(): string
    {
        do {
            $candidate = 'BDS-'.now()->year.'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (User::where('username', $candidate)->exists());

        return $candidate;
    }

    private function generateUniqueStaffNo(): string
    {
        do {
            $candidate = 'STF-'.now()->year.'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (Staff::where('staff_no', $candidate)->exists());

        return $candidate;
    }
}
