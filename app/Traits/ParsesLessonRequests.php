<?php

namespace App\Traits;

use App\Models\Message;
use App\Models\TutoringSession;

/**
 * Lesson requests are stored as Message rows with a "lesson request" subject
 * and a JSON-encoded body (no dedicated LessonRequest model exists). Shared
 * by the tutor-facing and admin-facing lesson request endpoints so both read
 * the same status/derivation logic instead of drifting apart.
 */
trait ParsesLessonRequests
{
    /**
     * @param  \App\Models\Tutor|null  $tutorScope  When given, only trust session
     *                                               matches against this tutor's sessions.
     */
    protected function transformLessonRequestMessage(Message $message, $tutorScope = null): array
    {
        $body = $message->body ?? '';

        $data = json_decode($body, true);
        if (! is_array($data)) {
            $data = [];
        }

        $parentName = $message->sender->name ?? 'Unknown Parent';
        $studentName = 'Unknown Student';

        if (isset($data['student_name'])) {
            $studentName = $data['student_name'];
        } elseif (preg_match('/student[:\s]+([^\n]+)/i', $body, $matches)) {
            $studentName = trim($matches[1]);
        }

        $lessonType = $data['lesson_type'] ?? $data['subject'] ?? 'General Lesson';
        $preferredDate = $data['preferred_date'] ?? $data['date'] ?? null;
        $preferredTime = $data['preferred_time'] ?? $data['time'] ?? null;

        $duration = $data['duration'] ?? null;
        if (! $duration && isset($data['duration_hours'])) {
            $durationHours = floatval($data['duration_hours']);
            $duration = $durationHours == 1 ? '1 hour' : $durationHours.' hours';
        }
        if (! $duration) {
            $duration = '1 hour';
        }

        $messageText = $data['message'] ?? $body;

        $status = 'pending';
        if (isset($data['status']) && in_array($data['status'], ['pending', 'approved', 'declined'])) {
            $status = $data['status'];
        } elseif ($tutorScope && $preferredDate) {
            if (isset($data['session_id'])) {
                $relatedSession = TutoringSession::where('teacher_id', $tutorScope->id)
                    ->where('id', $data['session_id'])
                    ->first();
                if ($relatedSession) {
                    $status = 'approved';
                }
            } elseif ($preferredTime) {
                $timeFormatted = preg_match('/^\d{2}:\d{2}$/', $preferredTime) ? $preferredTime.':00' : $preferredTime;
                $relatedSession = TutoringSession::where('teacher_id', $tutorScope->id)
                    ->where('date', $preferredDate)
                    ->where('start_time', 'like', substr($timeFormatted, 0, 5).'%')
                    ->first();
                if ($relatedSession) {
                    $status = 'approved';
                }
            }
        }

        return [
            'id' => $message->id,
            'student_name' => $studentName,
            'parent_name' => $parentName,
            'tutor_name' => $message->recipient->name ?? 'Unknown Tutor',
            'lesson_type' => $lessonType,
            'preferred_date' => $preferredDate,
            'preferred_time' => $preferredTime,
            'duration' => $duration,
            'duration_hours' => isset($data['duration_hours']) ? floatval($data['duration_hours']) : null,
            'message' => $messageText,
            'requested_at' => $message->created_at->toDateTimeString(),
            'status' => $status,
            'sender_id' => $message->sender_id,
            'recipient_id' => $message->recipient_id,
        ];
    }

    protected function lessonRequestMessageQuery()
    {
        return Message::where(function ($q) {
            $q->where('subject', 'like', '%lesson request%')
                ->orWhere('subject', 'like', '%Lesson Request%');
        })->with(['sender', 'recipient']);
    }
}
