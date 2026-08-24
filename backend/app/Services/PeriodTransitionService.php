<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Period;
use App\Models\Semester;
use Illuminate\Support\Carbon;

class PeriodTransitionService
{
    public function run(): void
    {
        $today = Carbon::today();

        Period::where('status', 'active')
            ->where('end_date', '<', $today)
            ->update(['status' => 'closed']);

        Period::where('status', 'upcoming')
            ->where('start_date', '<=', $today)
            ->update(['status' => 'active']);

        $this->closeSemestersWithClosedExamPeriod();
        $this->closeAcademicYearsWithClosedSemesters();
    }

    private function closeSemestersWithClosedExamPeriod(): void
    {
        Semester::where('status', '!=', 'closed')
            ->whereHas('periods', fn ($query) => $query->where('sequence', 3)->where('status', 'closed'))
            ->update(['status' => 'closed']);
    }

    private function closeAcademicYearsWithClosedSemesters(): void
    {
        AcademicYear::where('status', '!=', 'closed')
            ->whereDoesntHave('semesters', fn ($query) => $query->where('status', '!=', 'closed'))
            ->whereHas('semesters')
            ->update(['status' => 'closed']);
    }
}
