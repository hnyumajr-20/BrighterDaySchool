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
use Illuminate\Support\Str;

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
            'dob' => ['nullable', 'date'],
            'gender' => ['nullable', 'in:male,female'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'contact' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
            'staff_role' => ['required', 'in:registrar,accountant,teacher,librarian'],
            'salary_cents' => ['required', 'integer', 'min:0'],
        ]);

        $temporaryPassword = Str::password(12);

        $staff = DB::transaction(function () use ($data, $temporaryPassword) {
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
                'dob' => $data['dob'] ?? null,
                'gender' => $data['gender'] ?? null,
                'email' => $data['email'],
                'contact' => $data['contact'] ?? null,
                'address' => $data['address'] ?? null,
                'staff_role' => $data['staff_role'],
                'salary_cents' => $data['salary_cents'],
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
            'salary_cents' => ['sometimes', 'integer', 'min:0'],
            'status' => ['sometimes', 'in:active,inactive'],
        ]);

        $staff->update($data);

        return response()->json($staff);
    }

    public function destroy(Staff $staff): JsonResponse
    {
        $staff->delete();

        return response()->json(null, 204);
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
