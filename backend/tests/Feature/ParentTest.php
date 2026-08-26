<?php

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentTest extends TestCase
{
    use RefreshDatabase;

    private function tokenFor(string $role): string
    {
        return User::factory()->role($role)->create()->createToken('api')->plainTextToken;
    }

    public function test_admin_can_look_up_a_parent_by_phone(): void
    {
        Guardian::create(['full_name' => 'Mary Doe', 'phone' => '0770001111', 'email' => 'mary@brighterday.test']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('admin'))
            ->getJson('/api/v1/parents?phone=0770001111');

        $response->assertOk()->assertJsonPath('full_name', 'Mary Doe');
    }

    public function test_lookup_returns_404_when_no_parent_matches(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('registrar'))
            ->getJson('/api/v1/parents?phone=0000000000');

        $response->assertNotFound();
    }

    public function test_registrar_can_create_a_parent(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('registrar'))
            ->postJson('/api/v1/parents', [
                'full_name' => 'John Smith',
                'phone' => '0779998888',
                'email' => 'john@brighterday.test',
                'address' => 'Sinkor, Monrovia',
            ]);

        $response->assertCreated()->assertJsonPath('full_name', 'John Smith');
        $this->assertDatabaseHas('parents', ['phone' => '0779998888']);
    }

    public function test_parent_phone_must_be_unique(): void
    {
        Guardian::create(['full_name' => 'Existing Parent', 'phone' => '0771112222']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('admin'))
            ->postJson('/api/v1/parents', ['full_name' => 'Duplicate', 'phone' => '0771112222']);

        $response->assertUnprocessable()->assertJsonValidationErrors(['phone']);
    }

    public function test_teacher_cannot_access_parent_endpoints(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('teacher'))
            ->getJson('/api/v1/parents?phone=0770001111');

        $response->assertForbidden();
    }
}
