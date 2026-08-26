<?php

namespace Tests\Feature\Admin;

use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubjectTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        return User::factory()->role('admin')->create()->createToken('api')->plainTextToken;
    }

    public function test_admin_can_create_and_list_subjects(): void
    {
        $token = $this->adminToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/subjects', ['name' => 'Mathematics', 'code' => 'MATH'])
            ->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/subjects');
        $response->assertOk();
        $this->assertCount(1, $response->json());
    }

    public function test_admin_can_update_a_subject(): void
    {
        $token = $this->adminToken();
        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/subjects/{$subject->id}", ['name' => 'General Mathematics', 'code' => 'GMATH']);

        $response->assertOk()
            ->assertJsonPath('name', 'General Mathematics')
            ->assertJsonPath('code', 'GMATH');
    }

    public function test_updating_a_subject_rejects_a_code_already_used_by_another_subject(): void
    {
        $token = $this->adminToken();
        Subject::create(['name' => 'Mathematics', 'code' => 'MATH']);
        $english = Subject::create(['name' => 'English', 'code' => 'ENG']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/subjects/{$english->id}", ['code' => 'MATH']);

        $response->assertUnprocessable();
    }

    public function test_admin_can_delete_a_subject(): void
    {
        $token = $this->adminToken();
        $subject = Subject::create(['name' => 'Mathematics', 'code' => 'MATH']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson("/api/v1/subjects/{$subject->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
    }

    public function test_non_admin_cannot_create_subjects(): void
    {
        $token = User::factory()->role('teacher')->create()->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/subjects', ['name' => 'Mathematics']);

        $response->assertForbidden();
    }
}
