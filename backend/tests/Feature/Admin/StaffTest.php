<?php

namespace Tests\Feature\Admin;

use App\Jobs\SendLoggedEmailJob;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StaffTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        return User::factory()->role('admin')->create()->createToken('api')->plainTextToken;
    }

    public function test_admin_can_create_staff_and_credential_email_is_queued(): void
    {
        Queue::fake();

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->postJson('/api/v1/staff', [
                'full_name' => 'Grace Kollie',
                'email' => 'grace.kollie@brighterday.test',
                'contact' => '0770000000',
                'staff_role' => 'teacher',
                'salary_cents' => 50000000,
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('staff', [
            'full_name' => 'Grace Kollie',
            'staff_role' => 'teacher',
        ]);

        $staff = Staff::where('full_name', 'Grace Kollie')->firstOrFail();
        $this->assertNotNull($staff->user_id);

        $user = User::find($staff->user_id);
        $this->assertEquals('teacher', $user->role);
        $this->assertTrue($user->must_change_password);
        $this->assertStringStartsWith('BDS-', $user->username);

        $this->assertDatabaseHas('email_log', [
            'user_id' => $user->id,
            'type' => 'staff_credentials',
            'status' => 'queued',
        ]);
        Queue::assertPushed(SendLoggedEmailJob::class);
    }

    public function test_staff_creation_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@brighterday.test']);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->postJson('/api/v1/staff', [
                'full_name' => 'Someone Else',
                'email' => 'taken@brighterday.test',
                'staff_role' => 'librarian',
                'salary_cents' => 10000000,
            ]);

        $response->assertUnprocessable();
    }

    public function test_non_admin_cannot_create_staff(): void
    {
        $token = User::factory()->role('teacher')->create()->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/staff', [
                'full_name' => 'Blocked User',
                'email' => 'blocked@brighterday.test',
                'staff_role' => 'teacher',
                'salary_cents' => 10000000,
            ]);

        $response->assertForbidden();
    }

    public function test_admin_can_list_and_update_staff(): void
    {
        Queue::fake();
        $token = $this->adminToken();

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/staff', [
                'full_name' => 'Update Me',
                'email' => 'update.me@brighterday.test',
                'staff_role' => 'accountant',
                'salary_cents' => 20000000,
            ])->json();

        $list = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/staff');
        $list->assertOk();
        $this->assertGreaterThanOrEqual(1, count($list->json()));

        $update = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/staff/{$created['id']}", ['status' => 'inactive']);

        $update->assertOk()->assertJsonPath('status', 'inactive');
    }
}
