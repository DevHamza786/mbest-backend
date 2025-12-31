<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\TutoringSession;
use Illuminate\Http\Request;

class AdminCalendarController extends Controller
{
    public function index(Request $request)
    {
        $query = TutoringSession::with(['teacher.user', 'students.user']);

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

        // Filter by tutor
        if ($request->has('tutor_id')) {
            $query->where('teacher_id', $request->tutor_id);
        }

        // Filter by subject
        if ($request->has('subject')) {
            $query->where('subject', $request->subject);
        }

        // Filter by location
        if ($request->has('location')) {
            $query->where('location', $request->location);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhereHas('teacher.user', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('students.user', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = $request->get('per_page', 50);
        $sessions = $query->orderBy('date', 'asc')
                          ->orderBy('start_time', 'asc')
                          ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'teacher_id' => 'required|exists:tutors,id',
            'class_id' => 'nullable|exists:classes,id',
            'subject' => 'required|string|max:255',
            'year_level' => 'nullable|string|max:50',
            'location' => 'required|string|max:255',
            'session_type' => 'required|in:1:1,group',
            'status' => 'sometimes|in:scheduled,in-progress,completed,cancelled',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'exists:students,id',
        ]);

        $session = TutoringSession::create([
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'teacher_id' => $validated['teacher_id'],
            'class_id' => $validated['class_id'] ?? null,
            'subject' => $validated['subject'],
            'year_level' => $validated['year_level'] ?? null,
            'location' => $validated['location'],
            'session_type' => $validated['session_type'],
            'status' => $validated['status'] ?? 'scheduled',
        ]);

        // Attach students if provided
        if (isset($validated['student_ids']) && !empty($validated['student_ids'])) {
            $session->students()->attach($validated['student_ids']);
        } elseif ($validated['class_id']) {
            // If class_id is provided, attach all students from the class
            $class = \App\Models\ClassModel::find($validated['class_id']);
            if ($class) {
                $studentIds = $class->students()->pluck('students.id')->toArray();
                $session->students()->attach($studentIds);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $session->load(['teacher.user', 'students.user', 'classModel']),
            'message' => 'Session created successfully',
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $session = TutoringSession::with([
            'teacher.user',
            'students.user',
            'studentNotes'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $session,
        ]);
    }

    public function filterOptions(Request $request)
    {
        // Get all unique subjects from sessions
        $subjects = TutoringSession::distinct()
            ->whereNotNull('subject')
            ->pluck('subject')
            ->filter()
            ->sort()
            ->values();

        // Get all unique locations from sessions
        $locations = TutoringSession::distinct()
            ->whereNotNull('location')
            ->pluck('location')
            ->filter()
            ->sort()
            ->values();

        // Get all unique session types from sessions
        $sessionTypes = TutoringSession::distinct()
            ->whereNotNull('session_type')
            ->pluck('session_type')
            ->filter()
            ->sort()
            ->values();

        // Get all unique statuses from sessions
        $statuses = TutoringSession::distinct()
            ->whereNotNull('status')
            ->pluck('status')
            ->filter()
            ->sort()
            ->values();

        // Get all tutors (teachers) who have sessions
        $tutors = \App\Models\Tutor::whereHas('sessions')
            ->with('user:id,name,email')
            ->get()
            ->map(function ($tutor) {
                return [
                    'id' => (string) $tutor->id,
                    'name' => $tutor->user->name ?? 'Unknown',
                ];
            })
            ->sortBy('name')
            ->values();

        // Get all students who have sessions
        $students = \App\Models\Student::whereHas('sessions')
            ->with('user:id,name,email')
            ->get()
            ->map(function ($student) {
                return [
                    'id' => (string) $student->id,
                    'name' => $student->user->name ?? 'Unknown',
                ];
            })
            ->sortBy('name')
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'teachers' => $tutors,
                'students' => $students,
                'subjects' => $subjects,
                'locations' => $locations,
                'session_types' => $sessionTypes,
                'statuses' => $statuses,
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $session = TutoringSession::findOrFail($id);

        $validated = $request->validate([
            'date' => 'sometimes|date',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'subject' => 'sometimes|string|max:255',
            'year_level' => 'nullable|string|max:50',
            'location' => 'sometimes|in:online,centre,home',
            'session_type' => 'sometimes|in:1:1,group',
            'status' => 'sometimes|in:planned,completed,cancelled,no-show,rescheduled,unavailable',
            'teacher_id' => 'sometimes|exists:tutors,id',
            'student_ids' => 'sometimes|array',
            'student_ids.*' => 'exists:students,id',
        ]);

        // Update session
        $session->update($validated);

        // Update students if provided
        if (isset($validated['student_ids'])) {
            $session->students()->sync($validated['student_ids']);
        }

        return response()->json([
            'success' => true,
            'data' => $session->load(['teacher.user', 'students.user', 'studentNotes']),
            'message' => 'Session updated successfully',
        ]);
    }

    public function addNotes(Request $request, $id)
    {
        $session = TutoringSession::findOrFail($id);

        $validated = $request->validate([
            'lesson_note' => 'nullable|string',
            'topics_taught' => 'nullable|string',
            'homework_resources' => 'nullable|string',
            'student_notes' => 'nullable|array',
            'student_notes.*.student_id' => 'required|exists:students,id',
            'student_notes.*.behavior_issues' => 'nullable|string',
            'student_notes.*.homework_completed' => 'nullable|boolean',
            'student_notes.*.homework_notes' => 'nullable|string',
            'student_notes.*.private_notes' => 'nullable|string',
        ]);

        // Update session notes
        $session->update([
            'lesson_note' => $validated['lesson_note'] ?? $session->lesson_note,
            'topics_taught' => $validated['topics_taught'] ?? $session->topics_taught,
            'homework_resources' => $validated['homework_resources'] ?? $session->homework_resources,
        ]);

        // Update or create student notes
        if (isset($validated['student_notes'])) {
            foreach ($validated['student_notes'] as $note) {
                \App\Models\StudentNote::updateOrCreate(
                    [
                        'session_id' => $session->id,
                        'student_id' => $note['student_id'],
                    ],
                    [
                        'behavior_issues' => $note['behavior_issues'] ?? null,
                        'homework_completed' => $note['homework_completed'] ?? false,
                        'homework_notes' => $note['homework_notes'] ?? null,
                        'private_notes' => $note['private_notes'] ?? null,
                    ]
                );
            }
        }

        return response()->json([
            'success' => true,
            'data' => $session->load(['teacher.user', 'students.user', 'studentNotes.student.user']),
            'message' => 'Notes added successfully',
        ]);
    }

    public function markAttendance(Request $request, $id)
    {
        $session = TutoringSession::findOrFail($id);

        $validated = $request->validate([
            'attendance' => 'required|array',
            'attendance.*.student_id' => 'required|exists:students,id',
            'attendance.*.status' => 'required|in:present,absent,late,excused',
        ]);

        // Update attendance in session_student pivot table
        foreach ($validated['attendance'] as $attendance) {
            $session->students()->updateExistingPivot($attendance['student_id'], [
                'attendance_status' => $attendance['status'],
            ]);
        }

        // Mark attendance as marked
        $session->update(['attendance_marked' => true]);

        return response()->json([
            'success' => true,
            'data' => $session->load(['teacher.user', 'students.user']),
            'message' => 'Attendance marked successfully',
        ]);
    }

    public function markReadyForInvoicing(Request $request, $id)
    {
        $session = TutoringSession::findOrFail($id);

        $session->update(['ready_for_invoicing' => true]);

        return response()->json([
            'success' => true,
            'data' => $session->load(['teacher.user', 'students.user']),
            'message' => 'Session marked ready for invoicing',
        ]);
    }
}

