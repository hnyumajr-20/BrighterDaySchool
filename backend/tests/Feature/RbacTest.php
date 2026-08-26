<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_token_is_rejected_with_403_on_accountant_only_route(): void
    {
        $teacher = User::factory()->role('teacher')->create();
        $token = $teacher->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/fee-transactions');

        $response->assertForbidden();
    }

    public function test_accountant_token_is_allowed_on_accountant_only_route(): void
    {
        $accountant = User::factory()->role('accountant')->create();
        $token = $accountant->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/fee-transactions/students');

        $response->assertOk();
    }

    public function test_non_admin_token_is_rejected_with_403_on_admin_only_route(): void
    {
        $registrar = User::factory()->role('registrar')->create();
        $token = $registrar->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/reports/dashboard');

        $response->assertForbidden();
    }

    public function test_admin_token_is_allowed_on_admin_only_route(): void
    {
        $admin = User::factory()->role('admin')->create();
        $token = $admin->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/reports/dashboard');

        $response->assertOk();
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/reports/dashboard');

        $response->assertUnauthorized();
    }
}
