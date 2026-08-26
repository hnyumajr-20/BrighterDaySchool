<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClassSubjectController extends Controller
{
    public function index(SchoolClass $class): JsonResponse
    {
        return response()->json(
            $class->classSubjects()->with(['subject', 'teacher'])->get()
        );
    }

    public function store(Request $request, SchoolClass $class): JsonResponse
    {
        $data = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:staff,id'],
        ]);

        if ($class->classSubjects()->where('subject_id', $data['subject_id'])->exists()) {
            return response()->json([
                'message' => 'This subject is already assigned to this class.',
                'errors' => ['subject_id' => ['This subject is already assigned to this class.']],
            ], 422);
        }

        $classSubject = $class->classSubjects()->create($data);

        return response()->json($classSubject->load(['subject', 'teacher']), 201);
    }

    public function update(Request $request, SchoolClass $class, ClassSubject $classSubject): JsonResponse
    {
        abort_unless($classSubject->class_id === $class->id, 404);

        $data = $request->validate([
            'teacher_id' => ['nullable', 'integer', 'exists:staff,id'],
        ]);

        $classSubject->update($data);

        return response()->json($classSubject->load(['subject', 'teacher']));
    }

    public function destroy(SchoolClass $class, ClassSubject $classSubject): JsonResponse
    {
        abort_unless($classSubject->class_id === $class->id, 404);

        $classSubject->delete();

        return response()->json(null, 204);
    }
}
