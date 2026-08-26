<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Period;
use App\Models\Semester;
use Illuminate\Support\Carbon;

class PeriodTransitionService
{
    /**
     * Run the daily transitions. Every level (year, semester, period) opens
     * and closes by its own start_date/end_date, same as periods always
     * did — admin's manual status edit still works at any time (it's just
     * a plain update), this only fills in the "nobody touched it" case so
     * an admin isn't required to flip every semester/year open and closed
     * by hand.
     */
    public function run(): void
    {
        $today = Carbon::today();

        foreach ([AcademicYear::class, Semester::class, Period::class] as $model) {
            $model::where('status', 'active')
                ->where('end_date', '<', $today)
                ->update(['status' => 'closed']);

            $model::where('status', 'upcoming')
                ->where('start_date', '<=', $today)
                ->update(['status' => 'active']);
        }

        $this->syncCascadingClosures();
    }

    /**
     * Re-check the semester/year closure rules from Section 3 — a semester
     * closes once its exam (sequence 3) period is closed, a year closes
     * once both its semesters are closed. This catches cases the plain
     * date check above can miss (e.g. a period closed early by hand, or a
     * period's own end_date not quite matching its semester's). Called
     * after the daily scheduler run, and after any manual period status
     * edit, so the two paths never disagree with each other.
     */
    public function syncCascadingClosures(): void
    {
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
