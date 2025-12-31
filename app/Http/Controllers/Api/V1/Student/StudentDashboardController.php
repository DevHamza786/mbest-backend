<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\ClassModel;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Grade;
use App\Models\TutoringSession;
use App\Models\Notification;
use Illuminate\Http\Request;

class StudentDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        $stats = [
            'enrolled_classes' => $student->classes()->where('classes.status', 'active')->count(),
            'assignments_due' => Assignment::whereHas('classModel.students', function ($q) use ($student) {
                $q->where('students.id', $student->id);
            })
            ->where('status', 'published')
            ->where('due_date', '>=', now())
            ->whereDoesntHave('submissions', function ($q) use ($student) {
                $q->where('student_id', $student->id);
            })
            ->count(),
            'completed_assignments' => AssignmentSubmission::where('student_id', $student->id)
                ->where('status', 'submitted')
                ->count(),
            'overall_grade' => Grade::where('student_id', $student->id)
                ->avg('grade') ?? 0,
            'upcoming_classes' => TutoringSession::whereHas('students', function ($q) use ($student) {
                $q->where('students.id', $student->id);
            })
            ->where('status', 'planned')
            ->where('date', '>=', now()->toDateString())
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit(5)
            ->with(['teacher.user'])
            ->get(),
            'recent_grades' => Grade::where('student_id', $student->id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->with(['assignment'])
                ->get(),
            'recent_announcements' => Notification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'title' => $notification->title,
                        'message' => $notification->message,
                        'type' => $notification->type,
                        'priority' => $notification->priority,
                        'is_read' => $notification->is_read,
                        'important' => $notification->priority === 'high',
                        'created_at' => $notification->created_at,
                        'time_ago' => $notification->created_at->diffForHumans(),
                    ];
                }),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}

