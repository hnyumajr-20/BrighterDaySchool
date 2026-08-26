<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffAttendanceWindow;
use Illuminate\Support\Carbon;

class AttendanceWindowService
{
    /**
     * Manually (or automatically, when $openedBy is null and $at is
     * omitted) open today's check-in window. Whether triggered by an
     * admin or by the scheduler, the opening moment must fall within the
     * configured [check_in_earliest, check_in_latest] clock range — that
     * bound is a hard rule, not just the automatic default.
     *
     * @throws \InvalidArgumentException when today isn't a school day, a
     *         window already exists for today, or $at falls outside the
     *         allowed opening range.
     */
    public function openToday(?Staff $openedBy = null, ?Carbon $at = null): StaffAttendanceWindow
    {
        $today = Carbon::today();
        $at ??= Carbon::now();
        $config = config('attendance.staff');

        if (! in_array($today->dayOfWeekIso, $config['days'], true)) {
            throw new \InvalidArgumentException('Today isn\'t a school day — check-in only runs Monday to Friday.');
        }

        if (StaffAttendanceWindow::whereDate('date', $today)->exists()) {
            throw new \InvalidArgumentException('Today\'s check-in window is already open.');
        }

        $earliest = $today->copy()->setTimeFromTimeString($config['check_in_earliest']);
        $latest = $today->copy()->setTimeFromTimeString($config['check_in_latest']);

        if ($at->lt($earliest) || $at->gt($latest)) {
            throw new \InvalidArgumentException(sprintf(
                'Check-in can only be opened between %s and %s.',
                $earliest->format('g:i A'),
                $latest->format('g:i A'),
            ));
        }

        return StaffAttendanceWindow::create([
            'date' => $today,
            'check_in_opens_at' => $at,
            'check_in_closes_at' => $at->copy()->addMinutes($config['check_in_duration_minutes']),
            'check_out_opens_at' => $today->copy()->setTimeFromTimeString($config['check_out_start']),
            'check_out_closes_at' => $today->copy()->setTimeFromTimeString($config['check_out_start'])
                ->addMinutes($config['check_out_duration_minutes']),
            'opened_by' => $openedBy?->id,
        ]);
    }

    /**
     * Called on a frequent schedule (see routes/console.php). Fills in
     * whatever nobody has done by hand yet — auto-opens today's window if
     * it's due and nobody opened it manually, and auto-marks absentees
     * once the check-in window has closed. Never touches a window or an
     * attendance row that already exists, so a manual action always wins.
     */
    public function run(): void
    {
        $this->autoOpenIfDue();
        $this->markAbsenteesIfDue();
    }

    private function autoOpenIfDue(): void
    {
        $today = Carbon::today();
        $config = config('attendance.staff');

        if (! in_array($today->dayOfWeekIso, $config['days'], true)) {
            return;
        }
        if (StaffAttendanceWindow::whereDate('date', $today)->exists()) {
            return;
        }

        $earliest = $today->copy()->setTimeFromTimeString($config['check_in_earliest']);
        $latest = $today->copy()->setTimeFromTimeString($config['check_in_latest']);
        $now = Carbon::now();

        if ($now->lt($earliest) || $now->gt($latest)) {
            // Too early: nothing to do yet. Too late: the scheduler missed
            // its chance (e.g. it was down all morning) — better to leave
            // today with no window at all than fabricate one hours after
            // the fact and immediately mass-mark everyone absent.
            return;
        }

        $this->openToday(null, $earliest);
    }

    private function markAbsenteesIfDue(): void
    {
        $window = StaffAttendanceWindow::whereDate('date', Carbon::today())
            ->whereNull('absentees_marked_at')
            ->first();

        if (! $window || Carbon::now()->lt($window->check_in_closes_at)) {
            return;
        }

        $alreadyMarked = StaffAttendance::where('date', $window->date)->pluck('staff_id');

        Staff::where('status', 'active')
            ->whereNotIn('id', $alreadyMarked)
            ->get(['id'])
            ->each(fn (Staff $staff) => StaffAttendance::create([
                'staff_id' => $staff->id,
                'date' => $window->date,
                'status' => 'absent',
                'method' => 'manual',
                'recorded_by' => null,
            ]));

        $window->update(['absentees_marked_at' => Carbon::now()]);
    }
}
