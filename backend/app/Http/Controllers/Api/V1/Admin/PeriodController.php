<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Period;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodController extends Controller
{
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
}
