<?php

namespace Tests\Feature\Auth;

use App\Jobs\SendLoggedEmailJob;
use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_change_password(): void
    {
        $user = User::factory()->mustChangePassword()->create([
            'password_hash' => Hash::make('old-secret'),
        ]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/change-password', [
                'old_password' => 'old-secret',
                'new_password' => 'new-secret-123',
            ]);

        $response->assertOk();
        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('new-secret-123', $user->password_hash));
    }

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create(['password_hash' => Hash::make('old-secret')]);
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/change-password', [
                'old_password' => 'not-the-password',
                'new_password' => 'new-secret-123',
            ]);

        $response->assertUnprocessable();
    }

    public function test_forgot_password_queues_email_and_creates_reset_token(): void
    {
        Queue::fake();

        $user = User::factory()->create(['email' => 'someone@brighterday.test']);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'someone@brighterday.test',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('password_resets', ['user_id' => $user->id]);
        $this->assertDatabaseHas('email_log', ['user_id' => $user->id, 'type' => 'password_reset', 'status' => 'queued']);
        Queue::assertPushed(SendLoggedEmailJob::class);
    }

    public function test_forgot_password_does_not_leak_whether_email_exists(): void
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'nobody@brighterday.test',
        ]);

        $response->assertOk();
        $this->assertDatabaseCount('password_resets', 0);
    }

    public function test_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create();
        $reset = PasswordReset::create([
            'user_id' => $user->id,
            'token' => 'valid-token-123',
            'expires_at' => now()->addMinutes(30),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'valid-token-123',
            'new_password' => 'brand-new-password',
        ]);

        $response->assertOk();
        $user->refresh();
        $this->assertTrue(Hash::check('brand-new-password', $user->password_hash));
        $this->assertDatabaseCount('password_resets', 0);
    }

    public function test_reset_password_rejects_expired_token(): void
    {
        $user = User::factory()->create();
        PasswordReset::create([
            'user_id' => $user->id,
            'token' => 'expired-token',
            'expires_at' => now()->subMinutes(5),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'expired-token',
            'new_password' => 'brand-new-password',
        ]);

        $response->assertUnprocessable();
    }

    public function test_reset_password_rejects_unknown_token(): void
    {
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'does-not-exist',
            'new_password' => 'brand-new-password',
        ]);

        $response->assertUnprocessable();
    }
}
