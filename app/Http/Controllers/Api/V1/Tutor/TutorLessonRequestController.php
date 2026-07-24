<?php

namespace App\Http\Controllers\Api\V1\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Tutor;
use App\Models\Message;
use App\Models\TutoringSession;
use App\Traits\ParsesLessonRequests;
use Illuminate\Http\Request;

class TutorLessonRequestController extends Controller
{
    use ParsesLessonRequests;

    /**
     * Get lesson requests for the tutor
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $query = $this->lessonRequestMessageQuery()->where('recipient_id', $user->id);

        $perPage = $request->get('per_page', 15);
        $messages = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $lessonRequests = $messages->getCollection()->map(
            fn ($message) => $this->transformLessonRequestMessage($message, $tutor)
        );

        // Filter by status if provided
        if ($request->has('status') && $request->status !== 'all') {
            $lessonRequests = $lessonRequests->filter(function ($req) use ($request) {
                return $req['status'] === $request->status;
            });
        }

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
     * Approve a lesson request (creates a session)
     */
    public function approve(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $message = Message::where('id', $id)
            ->where('recipient_id', $user->id)
            ->firstOrFail();

        // Parse message body to get lesson details
        $body = $message->body ?? '';
        $data = json_decode($body, true) ?? [];

        // Validate request data
        $validated = $request->validate([
            'date' => 'sometimes|date',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
        ]);

        // Create tutoring session from lesson request
        // Format time to include seconds (H:i:s)
        $startTime = $validated['start_time'] ?? $data['preferred_time'] ?? '09:00';
        if ($startTime && !preg_match('/^\d{2}:\d{2}:\d{2}$/', $startTime)) {
            // If time is in H:i format, add seconds
            $startTime = $startTime . ':00';
        }
        
        // Calculate end time
        $durationHours = $data['duration_hours'] ?? 1;
        $endTime = $validated['end_time'] ?? null;
        if (!$endTime && $startTime) {
            $endTime = date('H:i:s', strtotime($startTime . ' +' . $durationHours . ' hours'));
        }
        if (!$endTime) {
            $endTime = '10:00:00';
        }
        // Ensure end_time has seconds
        if ($endTime && !preg_match('/^\d{2}:\d{2}:\d{2}$/', $endTime)) {
            $endTime = $endTime . ':00';
        }
        
        $legacyLoc = $data['location'] ?? 'online';
        $locationType = in_array($legacyLoc, ['online', 'centre', 'home'], true)
            ? ($legacyLoc === 'online' ? 'online' : 'onsite')
            : 'online';

        $sessionData = [
            'teacher_id' => $tutor->id,
            'date' => $validated['date'] ?? $data['preferred_date'] ?? date('Y-m-d'),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'subject' => $data['lesson_type'] ?? $data['subject'] ?? 'General Lesson',
            'location_type' => $locationType,
            'location_detail' => $data['location_detail'] ?? null,
            'session_type' => '1:1', // Enum values: '1:1' or 'group'
            'status' => 'planned',
        ];

        // Get student ID from message data or sender
        $studentId = $data['student_id'] ?? null;
        if (!$studentId && $message->sender) {
            // Try to find student by parent user
            $student = \App\Models\Student::whereHas('user', function ($q) use ($message) {
                $q->where('parent_id', $message->sender_id);
            })->first();
            $studentId = $student?->id;
        }

        $session = TutoringSession::create($sessionData);

        // Attach student if found
        if ($studentId) {
            $session->students()->attach($studentId);
        }

        // Update message body to mark as approved and store session_id
        $data['status'] = 'approved';
        $data['session_id'] = $session->id;
        $data['approved_at'] = now()->toDateTimeString();
        $message->update([
            'body' => json_encode($data),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Lesson request approved and session created',
            'data' => [
                'session_id' => $session->id,
                'status' => 'approved',
            ],
        ], 201);
    }

    /**
     * Decline a lesson request
     */
    public function decline(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $message = Message::where('id', $id)
            ->where('recipient_id', $user->id)
            ->firstOrFail();

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        // Send decline response message to parent
        // Update message body to mark as declined
        $body = $message->body ?? '';
        $data = json_decode($body, true) ?? [];
        $data['status'] = 'declined';
        $data['declined_at'] = now()->toDateTimeString();
        if (isset($validated['reason'])) {
            $data['decline_reason'] = $validated['reason'];
        }
        $message->update([
            'body' => json_encode($data),
        ]);

        // Send decline response message to parent
        $parent = $message->sender;
        if ($parent) {
            Message::create([
                'thread_id' => $message->thread_id,
                'sender_id' => $user->id,
                'recipient_id' => $parent->id,
                'subject' => 'Re: ' . $message->subject,
                'body' => 'Thank you for your lesson request. Unfortunately, I am unable to accommodate this request at this time.' . 
                         ($validated['reason'] ? "\n\nReason: " . $validated['reason'] : ''),
                'is_read' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lesson request declined',
            'data' => [
                'status' => 'declined',
            ],
        ]);
    }
}

