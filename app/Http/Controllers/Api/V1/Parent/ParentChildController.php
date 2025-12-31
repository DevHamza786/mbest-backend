<?php

namespace App\Http\Controllers\Api\V1\Parent;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Student;
use App\Models\Grade;
use App\Models\ClassModel;
use App\Models\Assignment;
use App\Models\TutoringSession;
use Illuminate\Http\Request;

class ParentChildController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $parent = $user->parentModel;

        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'Parent profile not found',
            ], 404);
        }

        $children = $parent->students()->with('user')->get();

        return response()->json([
            'success' => true,
            'data' => $children,
        ]);
    }

    public function stats(Request $request, $id)
    {
        $user = $request->user();
        $parent = $user->parentModel;

        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'Parent profile not found',
            ], 404);
        }

        $child = $parent->students()->findOrFail($id);

        $stats = [
            'overall_grade' => Grade::where('student_id', $child->id)->avg('grade') ?? 0,
            'attendance_rate' => $this->calculateAttendanceRate($child->id),
            'enrolled_classes' => $child->classes()->where('status', 'active')->count(),
            'active_assignments' => Assignment::whereHas('classModel.students', function ($q) use ($child) {
                $q->where('students.id', $child->id);
            })
            ->where('status', 'published')
            ->where('due_date', '>=', now())
            ->whereDoesntHave('submissions', function ($q) use ($child) {
                $q->where('student_id', $child->id)
                  ->where('status', 'submitted');
            })
            ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    private function calculateAttendanceRate($studentId)
    {
        $totalSessions = TutoringSession::whereHas('students', function ($q) use ($studentId) {
            $q->where('students.id', $studentId);
        })
        ->where('status', 'completed')
        ->count();

        $presentSessions = TutoringSession::whereHas('students', function ($q) use ($studentId) {
            $q->where('students.id', $studentId)
              ->where('attendance_status', 'present');
        })
        ->where('status', 'completed')
        ->count();

        if ($totalSessions === 0) {
            return 0;
        }

        return round(($presentSessions / $totalSessions) * 100, 2);
    }
}

