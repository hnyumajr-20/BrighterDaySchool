<?php

namespace Tests\Feature\Admin;

use App\Models\AcademicYear;
use App\Models\Period;
use App\Models\Semester;
use App\Services\PeriodTransitionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PeriodTransitionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): PeriodTransitionService
    {
        return new PeriodTransitionService;
    }

    public function test_period_transitions_upcoming_to_active_to_closed_as_time_passes(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active',
        ]);
        $semester = Semester::create([
            'academic_year_id' => $year->id, 'name' => '1st Semester', 'sequence' => 1,
            'start_date' => '2026-09-01', 'end_date' => '2027-01-31', 'status' => 'active',
        ]);
        $period = Period::create([
            'semester_id' => $semester->id, 'name' => 'Period 1', 'sequence' => 1, 'is_exam_period' => false,
            'start_date' => '2026-09-10', 'end_date' => '2026-09-20', 'status' => 'upcoming',
        ]);

        Carbon::setTestNow('2026-09-05');
        $this->service()->run();
        $this->assertEquals('upcoming', $period->fresh()->status);

        Carbon::setTestNow('2026-09-10');
        $this->service()->run();
        $this->assertEquals('active', $period->fresh()->status);

        Carbon::setTestNow('2026-09-20');
        $this->service()->run();
        $this->assertEquals('active', $period->fresh()->status, 'Still active on its own end_date.');

        Carbon::setTestNow('2026-09-21');
        $this->service()->run();
        $this->assertEquals('closed', $period->fresh()->status);
    }

    public function test_semester_closes_automatically_once_exam_period_closes(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active',
        ]);
        $semester = Semester::create([
            'academic_year_id' => $year->id, 'name' => '1st Semester', 'sequence' => 1,
            'start_date' => '2026-09-01', 'end_date' => '2026-12-15', 'status' => 'active',
        ]);
        Period::create([
            'semester_id' => $semester->id, 'name' => 'Period 1', 'sequence' => 1, 'is_exam_period' => false,
            'start_date' => '2026-09-01', 'end_date' => '2026-10-15', 'status' => 'active',
        ]);
        Period::create([
            'semester_id' => $semester->id, 'name' => 'Period 2', 'sequence' => 2, 'is_exam_period' => false,
            'start_date' => '2026-10-16', 'end_date' => '2026-11-30', 'status' => 'upcoming',
        ]);
        $exam = Period::create([
            'semester_id' => $semester->id, 'name' => 'Period 3 - Exam', 'sequence' => 3, 'is_exam_period' => true,
            'start_date' => '2026-12-01', 'end_date' => '2026-12-15', 'status' => 'upcoming',
        ]);

        // One run activates the exam period (it was still "upcoming"); a
        // later run — the next scheduler tick, in reality — closes it. A
        // single run only ever advances a period by one status step, same
        // as the PRD's literal pseudocode.
        Carbon::setTestNow('2026-12-01');
        $this->service()->run();
        $this->assertEquals('active', $exam->fresh()->status);

        Carbon::setTestNow('2026-12-16');
        $this->service()->run();

        $this->assertEquals('closed', $exam->fresh()->status);
        $this->assertEquals('closed', $semester->fresh()->status);
    }

    public function test_academic_year_closes_automatically_once_both_semesters_close(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active',
        ]);

        $semester1 = Semester::create([
            'academic_year_id' => $year->id, 'name' => '1st Semester', 'sequence' => 1,
            'start_date' => '2026-09-01', 'end_date' => '2026-12-15', 'status' => 'active',
        ]);
        Period::create([
            'semester_id' => $semester1->id, 'name' => 'P3', 'sequence' => 3, 'is_exam_period' => true,
            'start_date' => '2026-12-01', 'end_date' => '2026-12-15', 'status' => 'active',
        ]);

        $semester2 = Semester::create([
            'academic_year_id' => $year->id, 'name' => '2nd Semester', 'sequence' => 2,
            'start_date' => '2027-01-05', 'end_date' => '2027-06-30', 'status' => 'upcoming',
        ]);
        Period::create([
            'semester_id' => $semester2->id, 'name' => 'P3', 'sequence' => 3, 'is_exam_period' => true,
            'start_date' => '2027-06-16', 'end_date' => '2027-06-30', 'status' => 'active',
        ]);

        Carbon::setTestNow('2027-07-01');
        $this->service()->run();

        $this->assertEquals('closed', $semester1->fresh()->status);
        $this->assertEquals('closed', $semester2->fresh()->status);
        $this->assertEquals('closed', $year->fresh()->status);
    }

    public function test_academic_year_stays_open_while_a_semester_is_still_open(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active',
        ]);

        $semester1 = Semester::create([
            'academic_year_id' => $year->id, 'name' => '1st Semester', 'sequence' => 1,
            'start_date' => '2026-09-01', 'end_date' => '2026-12-15', 'status' => 'active',
        ]);
        Period::create([
            'semester_id' => $semester1->id, 'name' => 'P3', 'sequence' => 3, 'is_exam_period' => true,
            'start_date' => '2026-12-01', 'end_date' => '2026-12-15', 'status' => 'active',
        ]);

        Semester::create([
            'academic_year_id' => $year->id, 'name' => '2nd Semester', 'sequence' => 2,
            'start_date' => '2027-01-05', 'end_date' => '2027-06-30', 'status' => 'upcoming',
        ]);

        Carbon::setTestNow('2026-12-16');
        $this->service()->run();

        $this->assertEquals('closed', $semester1->fresh()->status);
        $this->assertEquals('active', $year->fresh()->status);
    }

    public function test_semester_opens_and_closes_by_its_own_dates_with_no_periods_involved(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active',
        ]);
        $semester = Semester::create([
            'academic_year_id' => $year->id, 'name' => '1st Semester', 'sequence' => 1,
            'start_date' => '2026-09-10', 'end_date' => '2026-09-20', 'status' => 'upcoming',
        ]);

        Carbon::setTestNow('2026-09-05');
        $this->service()->run();
        $this->assertEquals('upcoming', $semester->fresh()->status, 'Before its start date it stays upcoming.');

        Carbon::setTestNow('2026-09-10');
        $this->service()->run();
        $this->assertEquals('active', $semester->fresh()->status, 'It opens by itself on its start date.');

        Carbon::setTestNow('2026-09-20');
        $this->service()->run();
        $this->assertEquals('active', $semester->fresh()->status, 'Still active on its own end date.');

        Carbon::setTestNow('2026-09-21');
        $this->service()->run();
        $this->assertEquals('closed', $semester->fresh()->status, 'It closes by itself the day after its end date.');
    }

    public function test_academic_year_opens_and_closes_by_its_own_dates(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-10', 'end_date' => '2027-07-20', 'status' => 'upcoming',
        ]);

        Carbon::setTestNow('2026-09-09');
        $this->service()->run();
        $this->assertEquals('upcoming', $year->fresh()->status);

        Carbon::setTestNow('2026-09-10');
        $this->service()->run();
        $this->assertEquals('active', $year->fresh()->status);

        Carbon::setTestNow('2027-07-21');
        $this->service()->run();
        $this->assertEquals('closed', $year->fresh()->status);
    }

    public function test_manual_status_edit_still_works_and_is_not_overridden_by_the_scheduler(): void
    {
        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-01', 'end_date' => '2027-07-31', 'status' => 'active',
        ]);
        // Its own dates say this semester shouldn't open until 2027-01-05,
        // but an admin can still open it early by hand — the scheduler
        // must not silently revert that override.
        $semester = Semester::create([
            'academic_year_id' => $year->id, 'name' => '2nd Semester', 'sequence' => 2,
            'start_date' => '2027-01-05', 'end_date' => '2027-06-30', 'status' => 'active',
        ]);

        Carbon::setTestNow('2026-10-01');
        $this->service()->run();

        $this->assertEquals('active', $semester->fresh()->status, 'Manual open stays open even before the start date.');
    }
}
