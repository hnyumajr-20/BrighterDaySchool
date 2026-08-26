<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Period;
use App\Models\Semester;
use Illuminate\Http\JsonResponse;

class AcademicContextController extends Controller
{
    /**
     * The academic year/semester/period the school is currently operating
     * under — i.e. whichever of each is presently "active" (open), however
     * that came to be: the daily scheduler, or an admin's manual override.
     * Any level can come back null (e.g. a year is active but sits in the
     * gap between two semesters) — Section 3's "lock during the gap"
     * default means that's a real, valid state, not an error.
     */
    public function current(): JsonResponse
    {
        $academicYear = AcademicYear::where('status', 'active')->first();

        $semester = $academicYear
            ? Semester::where('academic_year_id', $academicYear->id)->where('status', 'active')->first()
            : null;

        $period = $semester
            ? Period::where('semester_id', $semester->id)->where('status', 'active')->first()
            : null;

        return response()->json([
            'academic_year' => $academicYear,
            'semester' => $semester,
            'period' => $period,
        ]);
    }
}
