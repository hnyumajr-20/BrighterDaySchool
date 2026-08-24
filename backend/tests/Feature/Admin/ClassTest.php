<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        return User::factory()->role('admin')->create()->createToken('api')->plainTextToken;
    }

    public function test_admin_can_create_a_class_with_a_fee(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->postJson('/api/v1/classes', [
                'name' => 'JSS1',
                'arm' => 'A',
                'fee_amount_cents' => 4500000,
                'academic_year_id' => $year->id,
            ]);

        $response->assertCreated()->assertJsonPath('fee_amount_cents', 4500000);
        $this->assertDatabaseHas('classes', ['name' => 'JSS1', 'arm' => 'A']);
    }

    public function test_duplicate_class_name_arm_year_is_rejected(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'status' => 'active',
        ]);
        $token = $this->adminToken();

        $payload = [
            'name' => 'JSS2',
            'arm' => 'B',
            'fee_amount_cents' => 4000000,
            'academic_year_id' => $year->id,
        ];

        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/classes', $payload)->assertCreated();
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/classes', $payload)->assertUnprocessable();
    }

    public function test_admin_can_list_and_update_a_class(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'status' => 'active',
        ]);
        $token = $this->adminToken();

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/classes', [
                'name' => 'JSS3',
                'arm' => 'A',
                'fee_amount_cents' => 4200000,
                'academic_year_id' => $year->id,
            ])->json();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/classes')
            ->assertOk();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/classes/{$created['id']}", ['fee_amount_cents' => 5000000])
            ->assertOk()
            ->assertJsonPath('fee_amount_cents', 5000000);
    }

    public function test_non_admin_cannot_create_a_class(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'status' => 'active',
        ]);
        $token = User::factory()->role('registrar')->create()->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/classes', [
                'name' => 'JSS1',
                'arm' => 'A',
                'fee_amount_cents' => 4500000,
                'academic_year_id' => $year->id,
            ]);

        $response->assertForbidden();
    }
}
