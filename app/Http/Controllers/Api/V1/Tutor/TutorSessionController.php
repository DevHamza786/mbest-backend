<?php

namespace App\Http\Controllers\Api\V1\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Tutor;
use App\Models\TutoringSession;
use App\Models\TutoringSessionFile;
use App\Models\StudentNote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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
        $relationships = ['students.user', 'studentNotes', 'sessionFiles'];
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

        // Year level filter (matches sessions.year_level)
        if ($request->has('year_level') && $request->year_level) {
            $query->where('year_level', $request->year_level);
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

            // Add public URLs for attached materials
            $sessions->each(function ($session) {
                $session->sessionFiles?->each(function ($file) {
                    if (!empty($file->file_path)) {
                        $file->file_url = Storage::url($file->file_path);
                    }
                });
            });
            
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

        // Add public URLs for attached materials
        $sessions->getCollection()->each(function ($session) {
            $session->sessionFiles?->each(function ($file) {
                if (!empty($file->file_path)) {
                    $file->file_url = Storage::url($file->file_path);
                }
            });
        });

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
            'location_type' => 'nullable|in:online,onsite',
            'location_detail' => 'nullable|string|max:5000',
            'location' => 'nullable|in:online,centre,home',
            'session_type' => 'required|in:1:1,group',
            'class_id' => 'nullable|exists:classes,id',
            'materials' => 'nullable|array',
            'materials.*' => 'file|max:10240|mimes:pdf,doc,docx,txt,rtf,ppt,pptx,xls,xlsx,jpg,jpeg,png',
        ]);

        $locationType = $validated['location_type'] ?? null;
        $locationDetail = $validated['location_detail'] ?? null;
        if (! $locationType && isset($validated['location'])) {
            $locationType = $validated['location'] === 'online' ? 'online' : 'onsite';
        }
        if (! $locationType) {
            return response()->json([
                'success' => false,
                'message' => 'Provide location_type (online|onsite) or legacy location (online|centre|home).',
            ], 422);
        }

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
            'location_type' => $locationType,
            'location_detail' => $locationDetail,
            'session_type' => $validated['session_type'],
            'status' => 'planned',
        ]);

        // Attach students
        $session->students()->attach($validated['student_ids']);

        // Store optional tutor-provided materials for the session
        if ($request->hasFile('materials')) {
            foreach ((array) $request->file('materials') as $material) {
                if (!$material) {
                    continue;
                }

                $storedPath = $material->store('tutoring_session_materials', 'public');

                $session->sessionFiles()->create([
                    'file_name' => $material->getClientOriginalName(),
                    'file_path' => $storedPath,
                    'mime_type' => $material->getClientMimeType(),
                    'file_size' => $material->getSize(),
                ]);
            }
        }

        $session->load(['students.user', 'sessionFiles']);
        $session->sessionFiles?->each(function ($file) {
            if (!empty($file->file_path)) {
                $file->file_url = Storage::url($file->file_path);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $session,
            'message' => 'Session created successfully',
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $session = TutoringSession::where('teacher_id', $tutor->id)
            ->with(['students.user', 'studentNotes.student.user', 'classModel', 'sessionFiles'])
            ->findOrFail($id);

        $session->sessionFiles?->each(function ($file) {
            if (!empty($file->file_path)) {
                $file->file_url = Storage::url($file->file_path);
            }
        });

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
            'location_type' => 'sometimes|in:online,onsite',
            'location_detail' => 'nullable|string|max:5000',
            'location' => 'nullable|in:online,centre,home',
            'session_type' => 'sometimes|in:1:1,group',
            'status' => 'sometimes|in:planned,completed,cancelled,no-show,rescheduled,unavailable',
            'class_id' => 'nullable|exists:classes,id',
        ]);

        if (isset($validated['location']) && ! isset($validated['location_type'])) {
            $validated['location_type'] = $validated['location'] === 'online' ? 'online' : 'onsite';
        }
        unset($validated['location']);

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
            'data' => tap($session->load(['students.user', 'sessionFiles']), function ($loaded) {
                $loaded->sessionFiles?->each(function ($file) {
                    if (!empty($file->file_path)) {
                        $file->file_url = Storage::url($file->file_path);
                    }
                });
            }),
            'message' => 'Session updated successfully',
        ]);
    }

    public function proposeReschedule(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $originalSession = TutoringSession::where('teacher_id', $tutor->id)->findOrFail($id);

        $validated = $request->validate([
            'proposed_date' => 'required|date',
            'proposed_start_time' => 'required|date_format:H:i',
            'proposed_end_time' => 'required|date_format:H:i|after:proposed_start_time',
        ]);

        $newSession = TutoringSession::create([
            'date' => $validated['proposed_date'],
            'start_time' => $validated['proposed_start_time'],
            'end_time' => $validated['proposed_end_time'],
            'teacher_id' => $tutor->id,
            'class_id' => $originalSession->class_id,
            'subject' => $originalSession->subject,
            'year_level' => $originalSession->year_level,
            'location_type' => $originalSession->location_type,
            'location_detail' => $originalSession->location_detail,
            'session_type' => $originalSession->session_type,
            'status' => 'rescheduled',
            // Notes/resources are meant for the completed session; keep them empty for the new proposal.
            'lesson_note' => null,
            'topics_taught' => null,
            'homework_resources' => null,
            'attendance_marked' => false,
            'ready_for_invoicing' => false,
            'color' => $originalSession->color,
        ]);

        // Copy students to the new session
        $studentIds = $originalSession->students()->pluck('students.id')->toArray();
        if (!empty($studentIds)) {
            $newSession->students()->attach($studentIds);
        }

        // Mark the original session as unavailable (teacher couldn't attend)
        $originalSession->update([
            'status' => 'unavailable',
        ]);

        $newSession->load(['students.user', 'sessionFiles']);
        $newSession->sessionFiles?->each(function ($file) {
            if (!empty($file->file_path)) {
                $file->file_url = Storage::url($file->file_path);
            }
        });

        return response()->json([
            'success' => true,
            'data' => $newSession,
            'message' => 'Reschedule proposed successfully',
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

        if ($session->attendance_marked) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance has already been recorded for this session.',
            ], 409);
        }

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

