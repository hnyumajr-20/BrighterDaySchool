<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Period;
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

    public function test_admin_can_list_academic_years_semesters_and_periods(): void
    {
        $token = $this->adminToken();

        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active',
        ]);
        $semester = Semester::create([
            'academic_year_id' => $year->id, 'name' => '1st Semester', 'sequence' => 1,
            'start_date' => '2026-09-01', 'end_date' => '2027-01-31', 'status' => 'active',
        ]);

        $years = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/academic-years');
        $years->assertOk();
        $this->assertCount(1, $years->json());

        $semesters = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/semesters?academic_year_id={$year->id}");
        $semesters->assertOk();
        $this->assertCount(1, $semesters->json());

        $periods = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/periods?semester_id={$semester->id}");
        $periods->assertOk();
        $this->assertCount(0, $periods->json());
    }

    public function test_listing_semesters_requires_academic_year_id(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken())
            ->getJson('/api/v1/semesters');

        $response->assertUnprocessable();
    }

    public function test_admin_can_manually_open_and_close_an_academic_year(): void
    {
        $token = $this->adminToken();
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'upcoming',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/academic-years/{$year->id}", ['status' => 'active'])
            ->assertOk()->assertJsonPath('status', 'active');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/academic-years/{$year->id}", ['status' => 'closed'])
            ->assertOk()->assertJsonPath('status', 'closed');
    }

    public function test_admin_can_manually_open_and_close_a_semester(): void
    {
        $token = $this->adminToken();
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active',
        ]);
        $semester = Semester::create([
            'academic_year_id' => $year->id, 'name' => '1st Semester', 'sequence' => 1,
            'start_date' => '2026-09-01', 'end_date' => '2027-01-31', 'status' => 'upcoming',
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/semesters/{$semester->id}", ['status' => 'active'])
            ->assertOk()->assertJsonPath('status', 'active');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/semesters/{$semester->id}", ['status' => 'closed'])
            ->assertOk()->assertJsonPath('status', 'closed');
    }

    public function test_manually_closing_the_exam_period_cascades_to_close_the_semester_and_year_when_both_close(): void
    {
        $token = $this->adminToken();
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active',
        ]);

        $semester1 = Semester::create([
            'academic_year_id' => $year->id, 'name' => '1st Semester', 'sequence' => 1,
            'start_date' => '2026-09-01', 'end_date' => '2026-12-15', 'status' => 'closed',
        ]);
        $exam1 = Period::create([
            'semester_id' => $semester1->id, 'name' => 'P3', 'sequence' => 3, 'is_exam_period' => true,
            'start_date' => '2026-12-01', 'end_date' => '2026-12-15', 'status' => 'closed',
        ]);

        $semester2 = Semester::create([
            'academic_year_id' => $year->id, 'name' => '2nd Semester', 'sequence' => 2,
            'start_date' => '2027-01-05', 'end_date' => '2027-06-30', 'status' => 'active',
        ]);
        $exam2 = Period::create([
            'semester_id' => $semester2->id, 'name' => 'P3', 'sequence' => 3, 'is_exam_period' => true,
            'start_date' => '2027-06-16', 'end_date' => '2027-06-30', 'status' => 'active',
        ]);

        // Manually closing the second semester's exam period should close
        // that semester, and — since the first semester is already closed —
        // the academic year too, exactly as the daily scheduler would.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/periods/{$exam2->id}", ['status' => 'closed'])
            ->assertOk()->assertJsonPath('status', 'closed');

        $this->assertDatabaseHas('semesters', ['id' => $semester2->id, 'status' => 'closed']);
        $this->assertDatabaseHas('academic_years', ['id' => $year->id, 'status' => 'closed']);
    }

    public function test_admin_can_edit_period_dates_and_name(): void
    {
        $token = $this->adminToken();
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active',
        ]);
        $semester = Semester::create([
            'academic_year_id' => $year->id, 'name' => '1st Semester', 'sequence' => 1,
            'start_date' => '2026-09-01', 'end_date' => '2027-01-31', 'status' => 'active',
        ]);
        $period = Period::create([
            'semester_id' => $semester->id, 'name' => 'Period 1', 'sequence' => 1, 'is_exam_period' => false,
            'start_date' => '2026-09-01', 'end_date' => '2026-11-15', 'status' => 'upcoming',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/periods/{$period->id}", [
                'name' => 'Period 1 - Renamed',
                'end_date' => '2026-11-20',
            ]);

        $response->assertOk()->assertJsonPath('name', 'Period 1 - Renamed');
        $this->assertStringStartsWith('2026-11-20', $response->json('end_date'));
    }

    public function test_non_admin_cannot_edit_academic_structure(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active',
        ]);
        $token = User::factory()->role('teacher')->create()->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/academic-years/{$year->id}", ['status' => 'closed'])
            ->assertForbidden();
    }
}
