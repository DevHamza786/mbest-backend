<?php

namespace App\Http\Controllers\Api\V1\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Tutor;
use App\Models\ClassModel;
use App\Models\Assignment;
use App\Models\TutoringSession;
use App\Models\Message;
use Illuminate\Http\Request;

class TutorDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $stats = [
            'total_students' => $tutor->classes()->withCount('students')->get()->sum('students_count'),
            'total_classes' => $tutor->classes()->where('status', 'active')->count(),
            'pending_assignments' => Assignment::where('tutor_id', $tutor->id)
                ->where('status', 'published')
                ->whereHas('submissions', function ($q) {
                    $q->where('status', 'submitted');
                })
                ->count(),
            'unread_messages' => Message::where('recipient_id', $user->id)
                ->where('is_read', false)
                ->count(),
            'upcoming_sessions' => TutoringSession::where('teacher_id', $tutor->id)
                ->where('status', 'planned')
                ->where('date', '>=', now()->toDateString())
                ->orderBy('date')
                ->orderBy('start_time')
                ->limit(5)
                ->with(['students.user'])
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => array_merge($stats, [
                'tutor' => [
                    'id' => $tutor->id,
                    'specialization' => $tutor->specialization,
                    'department' => $tutor->department,
                ],
            ]),
        ]);
    }
}

