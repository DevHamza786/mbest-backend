<?php

namespace App\Http\Controllers\Api\V1\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Tutor;
use App\Models\ClassModel;
use Illuminate\Http\Request;

class TutorClassController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $query = ClassModel::where('tutor_id', $tutor->id)
            ->with(['students.user', 'schedules']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);
        $classes = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $classes,
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $class = ClassModel::where('tutor_id', $tutor->id)
            ->with(['students.user', 'schedules', 'assignments', 'resources'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $class,
        ]);
    }

    public function students(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $class = ClassModel::where('tutor_id', $tutor->id)->findOrFail($id);
        $students = $class->students()->with('user')->get();

        return response()->json([
            'success' => true,
            'data' => $students,
        ]);
    }

    public function removeStudent(Request $request, $classId, $studentId)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $class = ClassModel::where('tutor_id', $tutor->id)->findOrFail($classId);
        
        // Remove student from class
        $class->students()->detach($studentId);

        return response()->json([
            'success' => true,
            'message' => 'Student removed from class successfully',
        ]);
    }
}

