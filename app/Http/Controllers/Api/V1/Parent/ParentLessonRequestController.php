<?php

namespace App\Http\Controllers\Api\V1\Parent;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\User;
use App\Traits\ParsesLessonRequests;
use Illuminate\Http\Request;

class ParentLessonRequestController extends Controller
{
    use ParsesLessonRequests;

    /**
     * Lesson requests this parent has sent to tutors.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = $this->lessonRequestMessageQuery()->where('sender_id', $user->id);

        $perPage = $request->get('per_page', 15);
        $messages = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $lessonRequests = $messages->getCollection()->map(
            fn ($message) => $this->transformLessonRequestMessage($message)
        );

        return response()->json([
            'success' => true,
            'data' => $lessonRequests->values(),
            'current_page' => $messages->currentPage(),
            'per_page' => $messages->perPage(),
            'total' => $messages->total(),
            'last_page' => $messages->lastPage(),
        ]);
    }

    /**
     * Send a new lesson request to a tutor.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'tutor_user_id' => 'required|exists:users,id',
            'student_name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'preferred_date' => 'required|date',
            'preferred_time' => 'required|string',
            'duration_hours' => 'nullable|numeric|min:0.5|max:8',
            'message' => 'nullable|string|max:2000',
        ]);

        $tutorUser = User::where('id', $validated['tutor_user_id'])->where('role', 'tutor')->firstOrFail();

        $body = [
            'student_name' => $validated['student_name'],
            'lesson_type' => $validated['subject'],
            'subject' => $validated['subject'],
            'preferred_date' => $validated['preferred_date'],
            'preferred_time' => $validated['preferred_time'],
            'duration_hours' => $validated['duration_hours'] ?? 1,
            'message' => $validated['message'] ?? '',
            'status' => 'pending',
        ];

        $message = Message::create([
            'thread_id' => 'thread-' . uniqid(),
            'sender_id' => $user->id,
            'recipient_id' => $tutorUser->id,
            'subject' => 'Lesson Request',
            'body' => json_encode($body),
            'is_read' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lesson request sent',
            'data' => $this->transformLessonRequestMessage($message->load(['sender', 'recipient'])),
        ], 201);
    }
}
