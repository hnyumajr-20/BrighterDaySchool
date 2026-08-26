<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffAttendanceWindow;
use App\Services\AttendanceWindowService;
use App\Support\DailySeries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['sometimes', 'date'],
        ]);
        $date = isset($data['date']) ? Carbon::parse($data['date']) : Carbon::today();

        $window = StaffAttendanceWindow::whereDate('date', $date)->first();
        $attendanceByStaffId = StaffAttendance::where('date', $date)->get()->keyBy('staff_id');

        $staff = Staff::where('status', 'active')->orderBy('full_name')->get()
            ->map(function (Staff $member) use ($attendanceByStaffId) {
                $attendance = $attendanceByStaffId->get($member->id);

                return [
                    'id' => $member->id,
                    'full_name' => $member->full_name,
                    'staff_role' => $member->staff_role,
                    'photo_url' => $member->photo_url,
                    'status' => $attendance?->status,
                    'method' => $attendance?->method,
                    'recorded_by' => $attendance?->recorded_by,
                ];
            });

        return response()->json([
            'window' => $window,
            'staff' => $staff,
        ]);
    }

    public function dailySummary(): JsonResponse
    {
        $days = 14;
        $rows = StaffAttendance::selectRaw('date, status, count(*) as count')
            ->where('date', '>=', Carbon::today()->subDays($days - 1))
            ->groupBy('date', 'status')
            ->get();

        $series = DailySeries::build($days, $rows, 'date', ['present' => 0, 'absent' => 0], function (array $day, $row) {
            if ($row->status === 'absent') {
                $day['absent'] += (int) $row->count;
            } else {
                // "present" and "late" both mean they showed up — same
                // convention already used for the dashboard's "Staff
                // Present Today" stat.
                $day['present'] += (int) $row->count;
            }

            return $day;
        });

        return response()->json($series);
    }

    public function openWindow(Request $request, AttendanceWindowService $service): JsonResponse
    {
        try {
            $window = $service->openToday($request->user()->staff);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($window, 201);
    }

    public function mark(Request $request): JsonResponse
    {
        $data = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'status' => ['required', 'in:present,absent,late'],
        ]);

        $attendance = StaffAttendance::updateOrCreate(
            ['staff_id' => $data['staff_id'], 'date' => Carbon::today()],
            ['status' => $data['status'], 'method' => 'manual', 'recorded_by' => $request->user()->staff?->id],
        );

        return response()->json($attendance);
    }
}
