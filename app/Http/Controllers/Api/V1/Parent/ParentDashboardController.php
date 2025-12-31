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

class ParentDashboardController extends Controller
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

        // Get children
        $children = $parent->students()->with('user')->get();

        // Get active child (first child or specified)
        $activeChildId = $request->get('child_id', $children->first()?->id);
        $activeChild = $activeChildId ? $children->firstWhere('id', $activeChildId) : null;

        $stats = null;
        if ($activeChild) {
            $stats = [
                'overall_grade' => Grade::where('student_id', $activeChild->id)->avg('grade') ?? 0,
                'attendance_rate' => $this->calculateAttendanceRate($activeChild->id),
                'enrolled_classes' => $activeChild->classes()->where('classes.status', 'active')->count(),
                'active_assignments' => Assignment::whereHas('classModel.students', function ($q) use ($activeChild) {
                    $q->where('students.id', $activeChild->id);
                })
                ->where('status', 'published')
                ->where('due_date', '>=', now())
                ->whereDoesntHave('submissions', function ($q) use ($activeChild) {
                    $q->where('student_id', $activeChild->id)
                      ->where('status', 'submitted');
                })
                ->count(),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'children' => $children,
                'active_child' => $activeChild,
                'stats' => $stats,
            ],
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

