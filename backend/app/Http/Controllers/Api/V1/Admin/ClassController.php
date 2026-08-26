<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SchoolClass::query();

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->integer('academic_year_id'));
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20'],
            'arm' => ['required', 'string', 'max:10'],
            'fee_amount_cents' => ['required', 'integer', 'min:0'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
        ]);

        $data['name'] = strtoupper($data['name']);
        $data['arm'] = strtoupper($data['arm']);

        if (SchoolClass::where('name', $data['name'])
            ->where('arm', $data['arm'])
            ->where('academic_year_id', $data['academic_year_id'])
            ->exists()) {
            return response()->json([
                'message' => 'A class with this name and arm already exists for that academic year.',
                'errors' => ['name' => ['A class with this name and arm already exists for that academic year.']],
            ], 422);
        }

        $class = SchoolClass::create($data);

        return response()->json($class, 201);
    }

    public function update(Request $request, SchoolClass $class): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:20'],
            'arm' => ['sometimes', 'string', 'max:10'],
            'fee_amount_cents' => ['sometimes', 'integer', 'min:0'],
            'academic_year_id' => ['sometimes', 'integer', 'exists:academic_years,id'],
        ]);

        $class->update($data);

        return response()->json($class);
    }

    public function destroy(SchoolClass $class): JsonResponse
    {
        $class->delete();

        return response()->json(null, 204);
    }
}
