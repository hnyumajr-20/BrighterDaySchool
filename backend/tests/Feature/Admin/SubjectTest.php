<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_and_list_subjects(): void
    {
        $token = User::factory()->role('admin')->create()->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/subjects', ['name' => 'Mathematics', 'code' => 'MATH'])
            ->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/subjects');
        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_non_admin_cannot_create_subjects(): void
    {
        $token = User::factory()->role('teacher')->create()->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/subjects', ['name' => 'Mathematics']);

        $response->assertForbidden();
    }
}
