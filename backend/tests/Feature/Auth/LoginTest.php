<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_log_in_with_username_and_receives_token(): void
    {
        $user = User::factory()->create([
            'username' => 'BDS-2026-0001',
            'password_hash' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username_or_email' => 'BDS-2026-0001',
            'password' => 'secret123',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'must_change_password', 'user']);
        $this->assertNotEmpty($response->json('token'));
        $this->assertEquals($user->id, $response->json('user.id'));
    }

    public function test_user_can_log_in_with_email(): void
    {
        User::factory()->create([
            'email' => 'admin@brighterday.test',
            'password_hash' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username_or_email' => 'admin@brighterday.test',
            'password' => 'secret123',
        ]);

        $response->assertOk();
    }

    public function test_login_fails_with_wrong_password(): void
    {
        User::factory()->create([
            'username' => 'BDS-2026-0002',
            'password_hash' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username_or_email' => 'BDS-2026-0002',
            'password' => 'wrong-password',
        ]);

        $response->assertUnprocessable();
    }

    public function test_login_fails_for_inactive_user(): void
    {
        User::factory()->inactive()->create([
            'username' => 'BDS-2026-0003',
            'password_hash' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username_or_email' => 'BDS-2026-0003',
            'password' => 'secret123',
        ]);

        $response->assertUnprocessable();
    }

    public function test_login_response_flags_forced_password_change(): void
    {
        User::factory()->mustChangePassword()->create([
            'username' => 'BDS-2026-0004',
            'password_hash' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'username_or_email' => 'BDS-2026-0004',
            'password' => 'secret123',
        ]);

        $response->assertOk()->assertJsonPath('must_change_password', true);
    }

    public function test_user_can_log_out_and_token_is_revoked(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/auth/logout');

        $response->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
