<?php

namespace App\Http\Controllers\Api\V1\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Tutor;
use App\Models\TutoringSession;
use App\Models\StudentNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TutorSessionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Get tutor - use first() to avoid exception if not found, but log it
        $tutor = Tutor::where('user_id', $user->id)->first();
        
        if (!$tutor) {
            \Log::error('Tutor not found for user', [
                'user_id' => $user->id,
                'user_email' => $user->email ?? 'N/A',
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Tutor profile not found for this user',
            ], 404);
        }

        // Debug logging
        \Log::info('Tutor Session Query', [
            'user_id' => $user->id,
            'tutor_id' => $tutor->id,
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
        ]);

        // Build base query - ensure we're filtering by the correct tutor_id
        $query = TutoringSession::where('teacher_id', $tutor->id);
        
        // Eager load relationships (this shouldn't filter results, but load them)
        // Check if class_id column exists before eager loading classModel
        $relationships = ['students.user', 'studentNotes'];
        if (Schema::hasColumn('tutoring_sessions', 'class_id')) {
            $relationships[] = 'classModel';
        }
        $query->with($relationships);

        // Date filters
        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        // Status filter
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Subject filter
        if ($request->has('subject')) {
            $query->where('subject', $request->subject);
        }

        // Student filter
        if ($request->has('student_id')) {
            $query->whereHas('students', function ($q) use ($request) {
                $q->where('students.id', $request->student_id);
            });
        }

        // Class filter - filter sessions by class_id (direct relationship)
        // Only apply filter if class_id is provided and is not null/empty/0
        if ($request->has('class_id') && $request->class_id !== null && $request->class_id !== '' && $request->class_id !== '0' && $request->class_id !== 0) {
            $query->where('class_id', $request->class_id);
        }

        // For calendar views with date filters, return all results (not paginated)
        // Otherwise, use pagination
        if ($request->has('date_from') || $request->has('date_to')) {
            // Return all sessions in the date range for calendar view
            $sessions = $query->orderBy('date')->orderBy('start_time')->get();
            
            // Debug: Log the actual query being executed
            \Log::info('Tutor Session Query SQL (All Results)', [
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings(),
                'count' => $sessions->count(),
                'tutor_id' => $tutor->id,
                'user_id' => $user->id,
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
            ]);
            
            // Also log a raw query to verify - use exact same conditions
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            
            $rawQuery = DB::table('tutoring_sessions')
                ->where('teacher_id', $tutor->id);
            
            if ($dateFrom) {
                $rawQuery->where('date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $rawQuery->where('date', '<=', $dateTo);
            }
            
            $rawCount = (clone $rawQuery)->count();
            
            $rawSessions = (clone $rawQuery)
                ->orderBy('date')
                ->orderBy('start_time')
                ->get(['id', 'date', 'start_time', 'teacher_id', 'subject']);
            
            // Get Eloquent query SQL for comparison
            $eloquentSql = $query->toSql();
            $eloquentBindings = $query->getBindings();
            
            \Log::info('Raw SQL Verification', [
                'tutor_id_used' => $tutor->id,
                'user_id' => $user->id,
                'raw_count' => $rawCount,
                'eloquent_count' => $sessions->count(),
                'raw_session_ids' => $rawSessions->pluck('id')->toArray(),
                'eloquent_session_ids' => $sessions->pluck('id')->toArray(),
                'raw_sessions' => $rawSessions->toArray(),
                'eloquent_sql' => $eloquentSql,
                'eloquent_bindings' => $eloquentBindings,
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $sessions,
                'debug' => [
                    'tutor_id' => $tutor->id,
                    'user_id' => $user->id,
                    'count' => $sessions->count(),
                    'raw_count' => $rawCount,
                ],
            ]);
        }

        // For list views, use pagination
        $perPage = $request->get('per_page', 15);
        $sessions = $query->orderBy('date')->orderBy('start_time')->paginate($perPage);

        // Debug: Log the actual query being executed
        \Log::info('Tutor Session Query SQL (Paginated)', [
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings(),
            'count' => $sessions->total(),
            'tutor_id' => $tutor->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $validated = $request->validate([
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
            'subject' => 'required|string|max:255',
            'year_level' => 'nullable|string|max:50',
            'location' => 'required|in:online,centre,home',
            'session_type' => 'required|in:1:1,group',
            'class_id' => 'nullable|exists:classes,id',
        ]);

        // Verify class belongs to tutor if provided
        if ($request->has('class_id') && $request->class_id) {
            $class = \App\Models\ClassModel::where('id', $request->class_id)
                ->where('tutor_id', $tutor->id)
                ->first();
            
            if (!$class) {
                return response()->json([
                    'success' => false,
                    'message' => 'Class not found or does not belong to this tutor',
                ], 404);
            }
        }

        $session = TutoringSession::create([
            'date' => $validated['date'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'teacher_id' => $tutor->id,
            'class_id' => $validated['class_id'] ?? null,
            'subject' => $validated['subject'],
            'year_level' => $validated['year_level'] ?? null,
            'location' => $validated['location'],
            'session_type' => $validated['session_type'],
            'status' => 'planned',
        ]);

        // Attach students
        $session->students()->attach($validated['student_ids']);

        return response()->json([
            'success' => true,
            'data' => $session->load(['students.user']),
            'message' => 'Session created successfully',
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $session = TutoringSession::where('teacher_id', $tutor->id)
            ->with(['students.user', 'studentNotes.student.user', 'classModel'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $session,
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $session = TutoringSession::where('teacher_id', $tutor->id)->findOrFail($id);

        $validated = $request->validate([
            'date' => 'sometimes|date',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'subject' => 'sometimes|string|max:255',
            'year_level' => 'nullable|string|max:50',
            'location' => 'sometimes|in:online,centre,home',
            'session_type' => 'sometimes|in:1:1,group',
            'status' => 'sometimes|in:planned,completed,cancelled,no-show,rescheduled,unavailable',
            'class_id' => 'nullable|exists:classes,id',
        ]);

        // Verify class belongs to tutor if provided
        if ($request->has('class_id') && $request->class_id) {
            $class = \App\Models\ClassModel::where('id', $request->class_id)
                ->where('tutor_id', $tutor->id)
                ->first();
            
            if (!$class) {
                return response()->json([
                    'success' => false,
                    'message' => 'Class not found or does not belong to this tutor',
                ], 404);
            }
        }

        $session->update($validated);

        return response()->json([
            'success' => true,
            'data' => $session->load(['students.user']),
            'message' => 'Session updated successfully',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $session = TutoringSession::where('teacher_id', $tutor->id)->findOrFail($id);
        $session->delete();

        return response()->json([
            'success' => true,
            'message' => 'Session deleted successfully',
        ]);
    }

    public function addNotes(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $session = TutoringSession::where('teacher_id', $tutor->id)->findOrFail($id);

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
                StudentNote::updateOrCreate(
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
            'data' => $session->load(['students.user', 'studentNotes.student.user']),
            'message' => 'Notes added successfully',
        ]);
    }

    public function markAttendance(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $session = TutoringSession::where('teacher_id', $tutor->id)->findOrFail($id);

        $validated = $request->validate([
            'attendance' => 'required|array',
            'attendance.*.student_id' => 'required|exists:students,id',
            'attendance.*.status' => 'required|in:present,absent,late,excused',
        ]);

        foreach ($validated['attendance'] as $attendance) {
            DB::table('session_student')
                ->where('session_id', $session->id)
                ->where('student_id', $attendance['student_id'])
                ->update(['attendance_status' => $attendance['status']]);
        }

        $session->update(['attendance_marked' => true]);

        return response()->json([
            'success' => true,
            'data' => $session->load(['students.user']),
            'message' => 'Attendance marked successfully',
        ]);
    }
}

