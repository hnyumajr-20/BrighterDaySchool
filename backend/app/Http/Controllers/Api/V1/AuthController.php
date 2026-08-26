<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\SendLoggedEmailJob;
use App\Models\EmailLog;
use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username_or_email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $data['username_or_email'])
            ->orWhere('email', $data['username_or_email'])
            ->first();

        if (! $user || ! Hash::check($data['password'], $user->password_hash)) {
            throw ValidationException::withMessages([
                'username_or_email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'username_or_email' => ['This account is inactive.'],
            ]);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'must_change_password' => $user->must_change_password,
            'user' => [
                'id' => $user->id,
                'role' => $user->role,
                'username' => $user->username,
                'email' => $user->email,
                'photo_url' => $user->staff?->photo_url,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'old_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['old_password'], $user->password_hash)) {
            throw ValidationException::withMessages([
                'old_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password_hash' => Hash::make($data['new_password']),
            'must_change_password' => false,
        ]);

        return response()->json(['message' => 'Password changed.']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user) {
            $token = Str::random(64);

            PasswordReset::create([
                'user_id' => $user->id,
                'token' => $token,
                'expires_at' => now()->addMinutes(60),
            ]);

            $emailLog = EmailLog::create([
                'user_id' => $user->id,
                'type' => 'password_reset',
                'status' => 'queued',
            ]);

            SendLoggedEmailJob::dispatch(
                $emailLog->id,
                $user->email,
                'Reset your Brighter Day SMIS password',
                "Use this token to reset your password: {$token}\nThis token expires in 60 minutes.",
            );
        }

        return response()->json([
            'message' => 'If that email exists, a reset link has been sent.',
        ]);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8'],
        ]);

        $reset = PasswordReset::where('token', $data['token'])
            ->where('expires_at', '>', now())
            ->first();

        if (! $reset) {
            throw ValidationException::withMessages([
                'token' => ['This reset token is invalid or has expired.'],
            ]);
        }

        $reset->user->update([
            'password_hash' => Hash::make($data['new_password']),
            'must_change_password' => false,
        ]);

        PasswordReset::where('user_id', $reset->user_id)->delete();

        return response()->json(['message' => 'Password reset.']);
    }
}
