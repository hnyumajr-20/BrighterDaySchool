<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Period;
use App\Services\PeriodTransitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
        ]);

        return response()->json(
            Period::where('semester_id', $data['semester_id'])->orderBy('sequence')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'semester_id' => ['required', 'integer', 'exists:semesters,id'],
            'name' => ['required', 'string', 'max:30'],
            'sequence' => ['required', 'integer', 'in:1,2,3'],
            'is_exam_period' => ['sometimes', 'boolean'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        $data['is_exam_period'] ??= $data['sequence'] === 3;

        $period = Period::create($data);

        return response()->json($period, 201);
    }

    public function update(Request $request, Period $period, PeriodTransitionService $transitions): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:30'],
            'is_exam_period' => ['sometimes', 'boolean'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'status' => ['sometimes', 'in:upcoming,active,closed'],
        ]);

        $period->update($data);

        // A manual close should trigger the same semester/year rollup the
        // daily scheduler applies (Section 3), so the two paths never
        // disagree about whether a semester or year is closed.
        if (($data['status'] ?? null) === 'closed') {
            $transitions->syncCascadingClosures();
        }

        return response()->json($period->fresh());
    }
}
