<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\TutoringSession;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Carbon\Carbon;

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

        // Filter by year level
        if ($request->has('year_level') && $request->year_level) {
            $query->where('year_level', $request->year_level);
        }

        // Filter by location (location_type: online|onsite, or legacy free-text match on detail)
        if ($request->has('location') && $request->location !== '') {
            $loc = $request->location;
            if (in_array($loc, ['online', 'onsite'], true)) {
                $query->where('location_type', $loc);
            } else {
                $query->where(function ($q) use ($loc) {
                    $q->where('location_detail', 'like', '%'.$loc.'%')
                        ->orWhere('location_type', 'like', '%'.$loc.'%');
                });
            }
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
            'location_type' => 'nullable|in:online,onsite',
            'location_detail' => 'nullable|string|max:5000',
            'location' => 'nullable|string|max:255', // legacy online|centre|home when location_type omitted
            'session_type' => 'required|in:1:1,group',
            'status' => 'sometimes|string|max:32',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'exists:students,id',
            'repeat_days' => 'nullable|array',
            'repeat_days.*' => 'integer|min:0|max:6',
            'repeat_until' => 'nullable|date|after_or_equal:date',
        ]);

        $allowedStatuses = ['planned', 'completed', 'cancelled', 'no-show', 'rescheduled', 'unavailable'];
        $status = $validated['status'] ?? 'planned';
        // Legacy UI values
        if ($status === 'scheduled' || $status === 'in-progress') {
            $status = 'planned';
        }
        if (! in_array($status, $allowedStatuses, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Allowed: '.implode(', ', $allowedStatuses).' (or legacy scheduled / in-progress → planned).',
            ], 422);
        }

        $locationType = $validated['location_type'] ?? null;
        $locationDetail = $validated['location_detail'] ?? null;
        if (! $locationType && ! empty($validated['location'])) {
            $legacy = $validated['location'];
            if (in_array($legacy, ['online', 'centre', 'home'], true)) {
                $locationType = $legacy === 'online' ? 'online' : 'onsite';
            }
        }
        if (! $locationType) {
            return response()->json([
                'success' => false,
                'message' => 'Provide location_type (online|onsite) or legacy location (online|centre|home).',
            ], 422);
        }

        // Build the list of session dates: just the given date, unless the
        // admin picked specific weekdays to repeat on through an end date.
        $sessionDates = [$validated['date']];
        if (!empty($validated['repeat_days']) && !empty($validated['repeat_until'])) {
            $repeatDays = $validated['repeat_days'];
            $cursor = \Carbon\Carbon::parse($validated['date']);
            $until = \Carbon\Carbon::parse($validated['repeat_until']);
            $sessionDates = [];
            while ($cursor->lte($until)) {
                if (in_array((int) $cursor->dayOfWeek, $repeatDays, true)) {
                    $sessionDates[] = $cursor->toDateString();
                }
                $cursor->addDay();
            }
            if (empty($sessionDates)) {
                $sessionDates = [$validated['date']];
            }
        }

        $studentIds = null;
        if (isset($validated['student_ids']) && !empty($validated['student_ids'])) {
            $studentIds = $validated['student_ids'];
        } elseif ($validated['class_id']) {
            $class = \App\Models\ClassModel::find($validated['class_id']);
            if ($class) {
                $studentIds = $class->students()->pluck('students.id')->toArray();
            }
        }

        $createdSessions = [];
        foreach ($sessionDates as $sessionDate) {
            $session = TutoringSession::create([
                'date' => $sessionDate,
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'teacher_id' => $validated['teacher_id'],
                'class_id' => $validated['class_id'] ?? null,
                'subject' => $validated['subject'],
                'year_level' => $validated['year_level'] ?? null,
                'location_type' => $locationType,
                'location_detail' => $locationDetail,
                'session_type' => $validated['session_type'],
                'status' => $status,
            ]);

            if (!empty($studentIds)) {
                $session->students()->attach($studentIds);
            }

            $createdSessions[] = $session->load(['teacher.user', 'students.user', 'classModel']);
        }

        return response()->json([
            'success' => true,
            'data' => count($createdSessions) === 1 ? $createdSessions[0] : $createdSessions,
            'sessions_created' => count($createdSessions),
            'message' => count($createdSessions) > 1
                ? count($createdSessions).' sessions created successfully'
                : 'Session created successfully',
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

        // Filter dropdown: online vs onsite (distinct location types)
        $locations = \Illuminate\Support\Collection::make(['online', 'onsite']);

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

        // Get all tutors (for session form dropdown and filters)
        $tutors = \App\Models\Tutor::with('user:id,name,email')
            ->get()
            ->map(function ($tutor) {
                return [
                    'id' => (string) $tutor->id,
                    'name' => $tutor->user->name ?? 'Unknown',
                ];
            })
            ->sortBy('name')
            ->values();

        // Get all students (for session form dropdown and filters)
        $students = \App\Models\Student::with('user:id,name,email')
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
            'location_type' => 'sometimes|in:online,onsite',
            'location_detail' => 'nullable|string|max:5000',
            'location' => 'nullable|string|max:255',
            'session_type' => 'sometimes|in:1:1,group',
            'status' => 'sometimes|in:planned,completed,cancelled,no-show,rescheduled,unavailable',
            'teacher_id' => 'sometimes|exists:tutors,id',
            'student_ids' => 'sometimes|array',
            'student_ids.*' => 'exists:students,id',
        ]);

        if (isset($validated['location'])) {
            if (! isset($validated['location_type'])) {
                $legacy = $validated['location'];
                if (in_array($legacy, ['online', 'centre', 'home'], true)) {
                    $validated['location_type'] = $legacy === 'online' ? 'online' : 'onsite';
                }
            }
            unset($validated['location']);
        }

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
        // Mark the session and generate a tutor invoice so it appears in admin billing.
        $session = TutoringSession::with(['teacher.user', 'teacher', 'students.user', 'classModel'])->findOrFail($id);

        // If invoice already exists for this session, do not duplicate it.
        $existingInvoice = null;
        try {
            $existingInvoice = Invoice::where('session_id', $session->id)->first();
        } catch (\Throwable $e) {
            // In case session_id column doesn't exist in a deployed DB, just fall back to creating one invoice.
            $existingInvoice = null;
        }

        $session->update(['ready_for_invoicing' => true]);

        $tutorId = (int) ($session->teacher_id ?? $session->teacher?->id);
        $hourlyRate = (float) ($session->teacher?->hourly_rate ?? 0);

        // Use session created_at to compute issue/due dates (matches your “created date + 1 week” expectation).
        $createdAt = $session->created_at ? Carbon::parse($session->created_at) : Carbon::now();
        $issueDate = $createdAt->toDateString();
        $dueDate = $createdAt->copy()->addWeek()->toDateString();

        if ($existingInvoice) {
            $sessionData = $session->load(['teacher.user', 'students.user']);
            $sessionData->setAttribute('invoice_id', $existingInvoice->id);
            $sessionData->setAttribute('invoice_number', $existingInvoice->invoice_number);
            $sessionData->setAttribute('invoice_due_date', $existingInvoice->due_date);

            return response()->json([
                'success' => true,
                'data' => $sessionData,
                'message' => 'Session already invoiced (existing invoice returned)',
            ]);
        }

        // Calculate duration hours from session date + times.
        $sessionDate = $session->date instanceof Carbon ? $session->date->format('Y-m-d') : (string) $session->date;
        $start = Carbon::parse($sessionDate . ' ' . $session->start_time);
        $end = Carbon::parse($sessionDate . ' ' . $session->end_time);
        $diffSeconds = $end->timestamp - $start->timestamp;
        $durationHours = $diffSeconds > 0 ? round($diffSeconds / 3600, 2) : 0.0;

        $totalAmount = round($durationHours * $hourlyRate, 2);

        $firstStudent = $session->students->first();
        $studentId = $firstStudent?->id;

        // Determine parent_id from the first student’s parent relationship.
        $parentId = null;
        if ($firstStudent) {
            $parent = $firstStudent->parents()->first();
            $parentId = $parent?->id;
        }

        // Generate a unique invoice number for this tutor+issue date.
        $sequence = Invoice::where('tutor_id', $tutorId)
            ->whereDate('issue_date', $issueDate)
            ->count() + 1;

        $invoiceNumber = 'INV-TUTOR-' . $tutorId . '-' . Carbon::parse($issueDate)->format('Ymd') . '-' . str_pad((string)$sequence, 4, '0', STR_PAD_LEFT);

        $invoice = Invoice::create([
            'invoice_number' => $invoiceNumber,
            'student_id' => $studentId,
            'parent_id' => $parentId,
            'tutor_id' => $tutorId,
            'amount' => $totalAmount,
            'currency' => 'USD',
            'status' => 'pending',
            'due_date' => $dueDate,
            'issue_date' => $issueDate,
            'period_start' => $sessionDate,
            'period_end' => $sessionDate,
            'description' => 'Tutor invoice for session',
            'tutor_address' => null,
            'notes' => null,
            'session_id' => $session->id,
        ]);

        $invoice->items()->create([
            'description' => 'Session: ' . ($session->subject ?? 'Tutoring') . ' (' . $durationHours . 'h)',
            'amount' => $totalAmount,
            'credits' => null,
        ]);

        // Force invoice created/updated timestamps to match session timestamps (so “Created” column aligns).
        try {
            $invoice->timestamps = false;
            $invoice->created_at = $session->created_at;
            $invoice->updated_at = $session->updated_at ?? $session->created_at;
            $invoice->save();
            $invoice->timestamps = true;
        } catch (\Throwable $e) {
            // Non-fatal: if timestamps override fails, still return the invoice.
        }

        $sessionData = $session->load(['teacher.user', 'students.user']);
        $sessionData->setAttribute('invoice_id', $invoice->id);
        $sessionData->setAttribute('invoice_number', $invoice->invoice_number);
        $sessionData->setAttribute('invoice_due_date', $invoice->due_date);

        return response()->json([
            'success' => true,
            'data' => $sessionData,
            'message' => 'Session marked ready for invoicing and invoice created',
        ]);
    }
}

