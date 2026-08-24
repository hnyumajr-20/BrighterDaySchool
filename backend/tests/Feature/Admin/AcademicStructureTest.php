<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicStructureTest extends TestCase
{
    use RefreshDatabase;

    private function adminToken(): string
    {
        return User::factory()->role('admin')->create()->createToken('api')->plainTextToken;
    }

    public function test_admin_can_build_academic_year_semester_period_tree(): void
    {
        $token = $this->adminToken();

        $year = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/academic-years', [
                'name' => '2026/2027',
                'start_date' => '2026-09-01',
                'end_date' => '2027-07-31',
            ])->assertCreated()->json();

        $semester = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/semesters', [
                'academic_year_id' => $year['id'],
                'name' => '1st Semester',
                'sequence' => 1,
                'start_date' => '2026-09-01',
                'end_date' => '2027-01-31',
            ])->assertCreated()->json();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/periods', [
                'semester_id' => $semester['id'],
                'name' => 'Period 1',
                'sequence' => 1,
                'start_date' => '2026-09-01',
                'end_date' => '2026-11-15',
            ])->assertCreated();

        $this->assertDatabaseCount('academic_years', 1);
        $this->assertDatabaseCount('semesters', 1);
        $this->assertDatabaseCount('periods', 1);
    }

    public function test_semester_sequence_must_be_1_or_2(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->postJson('/api/v1/semesters', [
                'academic_year_id' => $year->id,
                'name' => 'Bad Semester',
                'sequence' => 3,
                'start_date' => '2026-09-01',
                'end_date' => '2027-01-31',
            ]);

        $response->assertUnprocessable();
    }

    public function test_period_sequence_must_be_1_to_3(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027',
            'start_date' => '2026-09-01',
            'end_date' => '2027-07-31',
            'status' => 'active',
        ]);
        $semester = Semester::create([
            'academic_year_id' => $year->id,
            'name' => '1st Semester',
            'sequence' => 1,
            'start_date' => '2026-09-01',
            'end_date' => '2027-01-31',
            'status' => 'active',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->postJson('/api/v1/periods', [
                'semester_id' => $semester->id,
                'name' => 'Bad Period',
                'sequence' => 4,
                'start_date' => '2026-09-01',
                'end_date' => '2026-11-15',
            ]);

        $response->assertUnprocessable();
    }

    public function test_semester_rejects_nonexistent_academic_year(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->postJson('/api/v1/semesters', [
                'academic_year_id' => 999999,
                'name' => 'Orphan Semester',
                'sequence' => 1,
                'start_date' => '2026-09-01',
                'end_date' => '2027-01-31',
            ]);

        $response->assertUnprocessable();
    }
}
