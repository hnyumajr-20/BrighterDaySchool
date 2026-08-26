<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Period;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_the_active_year_semester_and_period(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active',
        ]);
        $semester = Semester::create([
            'academic_year_id' => $year->id, 'name' => '1st Semester', 'sequence' => 1,
            'start_date' => '2026-09-01', 'end_date' => '2027-01-31', 'status' => 'active',
        ]);
        Period::create([
            'semester_id' => $semester->id, 'name' => 'Period 1', 'sequence' => 1, 'is_exam_period' => false,
            'start_date' => '2026-09-01', 'end_date' => '2026-11-15', 'status' => 'active',
        ]);

        $token = User::factory()->role('teacher')->create()->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/academic-years/current');

        $response->assertOk()
            ->assertJsonPath('academic_year.name', '2026/2027')
            ->assertJsonPath('semester.name', '1st Semester')
            ->assertJsonPath('period.name', 'Period 1');
    }

    public function test_returns_null_period_during_a_gap_between_periods(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active',
        ]);
        $semester = Semester::create([
            'academic_year_id' => $year->id, 'name' => '1st Semester', 'sequence' => 1,
            'start_date' => '2026-09-01', 'end_date' => '2027-01-31', 'status' => 'active',
        ]);
        Period::create([
            'semester_id' => $semester->id, 'name' => 'Period 1', 'sequence' => 1, 'is_exam_period' => false,
            'start_date' => '2026-09-01', 'end_date' => '2026-11-15', 'status' => 'closed',
        ]);
        Period::create([
            'semester_id' => $semester->id, 'name' => 'Period 2', 'sequence' => 2, 'is_exam_period' => false,
            'start_date' => '2026-11-20', 'end_date' => '2026-12-20', 'status' => 'upcoming',
        ]);

        $token = User::factory()->role('admin')->create()->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/academic-years/current');

        $response->assertOk()
            ->assertJsonPath('academic_year.name', '2026/2027')
            ->assertJsonPath('semester.name', '1st Semester')
            ->assertJsonPath('period', null);
    }

    public function test_returns_all_null_when_no_academic_year_is_active(): void
    {
        AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'upcoming',
        ]);

        $token = User::factory()->role('admin')->create()->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/academic-years/current');

        $response->assertOk()
            ->assertJsonPath('academic_year', null)
            ->assertJsonPath('semester', null)
            ->assertJsonPath('period', null);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/academic-years/current')->assertUnauthorized();
    }
}
