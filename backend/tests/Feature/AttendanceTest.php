<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffAttendanceWindow;
use App\Models\User;
use App\Services\AttendanceWindowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private const MONDAY = '2026-08-31';

    private const SATURDAY = '2026-08-29';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function service(): AttendanceWindowService
    {
        return new AttendanceWindowService;
    }

    private function tokenFor(string $role): string
    {
        return User::factory()->role($role)->create()->createToken('api')->plainTextToken;
    }

    private function makeStaff(string $name = 'Test Teacher'): Staff
    {
        return Staff::create(['full_name' => $name, 'staff_role' => 'teacher']);
    }

    public function test_admin_can_open_the_check_in_window_within_the_allowed_range(): void
    {
        Carbon::setTestNow(self::MONDAY.' 07:15:00');
        $token = $this->tokenFor('admin');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/staff/window/open');

        $response->assertCreated();
        $this->assertDatabaseHas('staff_attendance_windows', ['date' => self::MONDAY]);

        $window = StaffAttendanceWindow::first();
        $this->assertEquals('2026-08-31 07:15:00', $window->check_in_opens_at->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-08-31 08:45:00', $window->check_in_closes_at->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-08-31 13:30:00', $window->check_out_opens_at->format('Y-m-d H:i:s'));
        $this->assertEquals('2026-08-31 14:00:00', $window->check_out_closes_at->format('Y-m-d H:i:s'));
    }

    public function test_opening_before_seven_am_is_rejected(): void
    {
        Carbon::setTestNow(self::MONDAY.' 06:59:00');
        $token = $this->tokenFor('registrar');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/staff/window/open');

        $response->assertUnprocessable();
        $this->assertDatabaseCount('staff_attendance_windows', 0);
    }

    public function test_opening_after_eight_thirty_am_is_rejected(): void
    {
        Carbon::setTestNow(self::MONDAY.' 08:31:00');
        $token = $this->tokenFor('admin');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/staff/window/open');

        $response->assertUnprocessable();
        $this->assertDatabaseCount('staff_attendance_windows', 0);
    }

    public function test_opening_on_a_weekend_is_rejected(): void
    {
        Carbon::setTestNow(self::SATURDAY.' 07:30:00');
        $token = $this->tokenFor('admin');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/staff/window/open');

        $response->assertUnprocessable();
    }

    public function test_the_window_cannot_be_opened_twice_in_one_day(): void
    {
        Carbon::setTestNow(self::MONDAY.' 07:15:00');
        $token = $this->tokenFor('admin');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/staff/window/open')->assertCreated();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/staff/window/open');

        $response->assertUnprocessable();
        $this->assertDatabaseCount('staff_attendance_windows', 1);
    }

    public function test_the_scheduler_auto_opens_the_window_when_nobody_does_it_by_hand(): void
    {
        Carbon::setTestNow(self::MONDAY.' 07:00:00');

        $this->service()->run();

        $window = StaffAttendanceWindow::first();
        $this->assertNotNull($window);
        $this->assertNull($window->opened_by);
        $this->assertEquals('07:00:00', $window->check_in_opens_at->format('H:i:s'));
    }

    public function test_the_scheduler_never_backfills_a_window_once_the_day_has_moved_past_eight_thirty(): void
    {
        // The scheduler missed its whole morning run (e.g. it was down) —
        // it should give up on today rather than fabricate a window at
        // 10pm and immediately mass-mark every active staff member absent.
        Carbon::setTestNow(self::MONDAY.' 22:10:00');
        $this->makeStaff('Never Got A Chance');

        $this->service()->run();

        $this->assertDatabaseCount('staff_attendance_windows', 0);
        $this->assertDatabaseCount('staff_attendance', 0);
    }

    public function test_the_scheduler_does_not_open_a_window_before_seven_am(): void
    {
        Carbon::setTestNow(self::MONDAY.' 06:45:00');

        $this->service()->run();

        $this->assertDatabaseCount('staff_attendance_windows', 0);
    }

    public function test_the_scheduler_does_not_touch_a_manually_opened_window(): void
    {
        Carbon::setTestNow(self::MONDAY.' 07:20:00');
        $staff = $this->makeStaff('Manual Opener');
        $this->service()->openToday($staff, Carbon::now());

        Carbon::setTestNow(self::MONDAY.' 09:00:00');
        $this->service()->run();

        $this->assertDatabaseCount('staff_attendance_windows', 1);
        $window = StaffAttendanceWindow::first();
        $this->assertEquals($staff->id, $window->opened_by);
        $this->assertEquals('07:20:00', $window->check_in_opens_at->format('H:i:s'));
    }

    public function test_absentees_are_auto_marked_only_after_the_window_closes(): void
    {
        Carbon::setTestNow(self::MONDAY.' 07:00:00');
        $unmarked = $this->makeStaff('Never Showed Up');
        $this->service()->run();

        // Still inside the 90-minute window — nobody should be marked yet.
        Carbon::setTestNow(self::MONDAY.' 08:00:00');
        $this->service()->run();
        $this->assertDatabaseCount('staff_attendance', 0);

        // Past the close time — the unmarked staff member is now absent.
        Carbon::setTestNow(self::MONDAY.' 08:31:00');
        $this->service()->run();

        $this->assertDatabaseHas('staff_attendance', [
            'staff_id' => $unmarked->id,
            'status' => 'absent',
            'method' => 'manual',
            'recorded_by' => null,
        ]);
    }

    public function test_absentees_are_only_marked_once(): void
    {
        Carbon::setTestNow(self::MONDAY.' 07:00:00');
        $this->makeStaff('Solo Staff Member');
        $this->service()->run();

        Carbon::setTestNow(self::MONDAY.' 09:00:00');
        $this->service()->run();
        $this->service()->run();

        $this->assertDatabaseCount('staff_attendance', 1);
    }

    public function test_a_staff_member_marked_present_before_the_window_closes_is_not_marked_absent(): void
    {
        Carbon::setTestNow(self::MONDAY.' 07:00:00');
        $onTime = $this->makeStaff('On Time');
        $this->service()->run();

        StaffAttendance::create([
            'staff_id' => $onTime->id, 'date' => Carbon::today(), 'status' => 'present', 'method' => 'manual',
        ]);

        Carbon::setTestNow(self::MONDAY.' 09:00:00');
        $this->service()->run();

        $this->assertDatabaseHas('staff_attendance', ['staff_id' => $onTime->id, 'status' => 'present']);
        $this->assertDatabaseCount('staff_attendance', 1);
    }

    public function test_admin_can_mark_and_correct_a_staff_members_status(): void
    {
        Carbon::setTestNow(self::MONDAY.' 07:30:00');
        $staff = $this->makeStaff('Correctable Staff');
        $token = $this->tokenFor('registrar');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/staff/mark', ['staff_id' => $staff->id, 'status' => 'absent'])
            ->assertOk()->assertJsonPath('status', 'absent');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/staff/mark', ['staff_id' => $staff->id, 'status' => 'late']);

        $response->assertOk()->assertJsonPath('status', 'late');
        $this->assertDatabaseCount('staff_attendance', 1);
    }

    public function test_marking_records_the_acting_users_linked_staff_id(): void
    {
        Carbon::setTestNow(self::MONDAY.' 07:30:00');
        $target = $this->makeStaff('Marked Staff');
        $registrarStaff = $this->makeStaff('Registrar Person');
        $registrarUser = User::factory()->role('registrar')->create();
        $registrarStaff->update(['user_id' => $registrarUser->id]);
        $token = $registrarUser->createToken('api')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/staff/mark', ['staff_id' => $target->id, 'status' => 'present'])
            ->assertOk();

        $this->assertDatabaseHas('staff_attendance', ['staff_id' => $target->id, 'recorded_by' => $registrarStaff->id]);
    }

    public function test_the_daily_roster_shows_window_and_marked_status(): void
    {
        Carbon::setTestNow(self::MONDAY.' 07:30:00');
        $staff = $this->makeStaff('Roster Staff');
        $token = $this->tokenFor('admin');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/staff/window/open')->assertCreated();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/attendance/staff/mark', ['staff_id' => $staff->id, 'status' => 'present'])
            ->assertOk();

        $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/attendance/staff');

        $response->assertOk();
        $this->assertNotNull($response->json('window'));
        $roster = collect($response->json('staff'));
        $this->assertEquals('present', $roster->firstWhere('id', $staff->id)['status']);
    }

    public function test_teacher_and_accountant_cannot_access_attendance(): void
    {
        $teacherResponse = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('teacher'))
            ->getJson('/api/v1/attendance/staff');
        $teacherResponse->assertForbidden();

        $accountantResponse = $this->withHeader('Authorization', 'Bearer '.$this->tokenFor('accountant'))
            ->getJson('/api/v1/attendance/staff');
        $accountantResponse->assertForbidden();
    }

    public function test_daily_summary_returns_fourteen_zero_filled_days_with_present_and_absent_counts(): void
    {
        Carbon::setTestNow(self::MONDAY);
        $staffA = $this->makeStaff('Staff A');
        $staffB = $this->makeStaff('Staff B');
        $token = $this->tokenFor('admin');

        StaffAttendance::create(['staff_id' => $staffA->id, 'date' => self::MONDAY, 'status' => 'present', 'method' => 'manual']);
        StaffAttendance::create(['staff_id' => $staffB->id, 'date' => self::MONDAY, 'status' => 'late', 'method' => 'manual']);
        $yesterday = Carbon::parse(self::MONDAY)->subDay()->toDateString();
        StaffAttendance::create(['staff_id' => $staffA->id, 'date' => $yesterday, 'status' => 'absent', 'method' => 'manual']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/attendance/staff/daily-summary');

        $response->assertOk();
        $series = $response->json();
        $this->assertCount(14, $series);
        $this->assertEquals(self::MONDAY, $series[13]['date']);

        $today = collect($series)->firstWhere('date', self::MONDAY);
        $this->assertEquals(2, $today['present']); // present + late both count as present
        $this->assertEquals(0, $today['absent']);

        $yesterdayEntry = collect($series)->firstWhere('date', $yesterday);
        $this->assertEquals(1, $yesterdayEntry['absent']);
        $this->assertEquals(0, $yesterdayEntry['present']);

        $emptyDay = $series[0];
        $this->assertEquals(0, $emptyDay['present']);
        $this->assertEquals(0, $emptyDay['absent']);
    }
}
