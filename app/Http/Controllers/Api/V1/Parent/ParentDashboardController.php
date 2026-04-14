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
use Carbon\Carbon;

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

        $recentGrades = null;
        $upcomingSessions = null;
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

            // Get recent grades (last 10)
            $recentGrades = Grade::where('student_id', $activeChild->id)
                ->with(['assignment', 'classModel'])
                ->orderBy('date', 'desc')
                ->limit(10)
                ->get();

            // Get upcoming sessions from TutoringSession
            $upcomingSessions = TutoringSession::whereHas('students', function ($q) use ($activeChild) {
                $q->where('students.id', $activeChild->id);
            })
            ->where('status', 'planned')
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit(5)
            ->with(['classModel' => function ($q) {
                $q->select('id', 'name', 'code');
            }, 'teacher.user'])
            ->get()
            ->map(function ($session) {
                return [
                    'id' => $session->id,
                    'class_id' => $session->class_id,
                    'class_name' => $session->classModel?->name,
                    'class_code' => $session->classModel?->code,
                    'date' => $session->date,
                    'day_name' => Carbon::parse($session->date)->format('l'),
                    'start_time' => $session->start_time,
                    'end_time' => $session->end_time,
                    'location' => $session->location,
                    'subject' => $session->subject,
                    'teacher_name' => $session->teacher?->user?->name,
                    'view_url' => '/parents/classes/' . $session->class_id,
                ];
            });
        }

        return response()->json([
            'success' => true,
            'data' => [
                'children' => $children,
                'active_child' => $activeChild,
                'stats' => $stats,
                'recent_grades' => $recentGrades,
                'upcoming_sessions' => $upcomingSessions,
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

