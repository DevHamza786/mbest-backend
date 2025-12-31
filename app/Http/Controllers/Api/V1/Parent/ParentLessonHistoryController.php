<?php

namespace App\Http\Controllers\Api\V1\Parent;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TutoringSession;
use App\Models\ParentModel;
use Illuminate\Http\Request;

class ParentLessonHistoryController extends Controller
{
    public function index(Request $request, $id)
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

        $query = TutoringSession::whereHas('students', function ($q) use ($child) {
            $q->where('students.id', $child->id);
        })
        ->with(['teacher.user', 'classModel', 'studentNotes' => function ($q) use ($child) {
            $q->where('student_id', $child->id);
        }]);

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

        // Filter by tutor
        if ($request->has('tutor_id')) {
            $query->where('teacher_id', $request->tutor_id);
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

    public function show(Request $request, $childId, $sessionId)
    {
        $user = $request->user();
        $parent = $user->parentModel;

        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'Parent profile not found',
            ], 404);
        }

        $child = $parent->students()->findOrFail($childId);

        $session = TutoringSession::whereHas('students', function ($q) use ($child) {
            $q->where('students.id', $child->id);
        })
        ->with([
            'teacher.user',
            'classModel',
            'studentNotes' => function ($q) use ($child) {
                $q->where('student_id', $child->id);
            }
        ])
        ->findOrFail($sessionId);

        return response()->json([
            'success' => true,
            'data' => $session,
        ]);
    }
}

