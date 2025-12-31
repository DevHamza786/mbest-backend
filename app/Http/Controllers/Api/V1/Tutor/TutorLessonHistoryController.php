<?php

namespace App\Http\Controllers\Api\V1\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Tutor;
use App\Models\TutoringSession;
use Illuminate\Http\Request;

class TutorLessonHistoryController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $query = TutoringSession::where('teacher_id', $tutor->id)
            ->with(['students.user', 'studentNotes']);

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by subject
        if ($request->has('subject')) {
            $query->where('subject', $request->subject);
        }

        // Filter by student
        if ($request->has('student_id')) {
            $query->whereHas('students', function ($q) use ($request) {
                $q->where('students.id', $request->student_id);
            });
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('lesson_note', 'like', "%{$search}%")
                  ->orWhere('topics_taught', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $sessions = $query->orderBy('date', 'desc')
                          ->orderBy('start_time', 'desc')
                          ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }
}

