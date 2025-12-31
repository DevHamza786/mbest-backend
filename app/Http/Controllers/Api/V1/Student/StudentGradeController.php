<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Grade;
use Illuminate\Http\Request;

class StudentGradeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        $query = Grade::where('student_id', $student->id)
            ->with(['assignment']);

        // Filter by subject/category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        $perPage = $request->get('per_page', 15);
        $grades = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $overallAverage = Grade::where('student_id', $student->id)->avg('grade') ?? 0;

        return response()->json([
            'success' => true,
            'data' => [
                'data' => $grades,
                'overall_average' => round($overallAverage, 2),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        $grade = Grade::where('student_id', $student->id)
            ->with(['assignment'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $grade,
        ]);
    }
}

