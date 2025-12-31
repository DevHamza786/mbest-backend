<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\TutoringSession;
use App\Models\Student;
use App\Models\ClassModel;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $query = TutoringSession::with(['teacher.user', 'students.user', 'classModel'])
            ->where('attendance_marked', true);

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        // Filter by tutor
        if ($request->has('tutor_id')) {
            $query->where('teacher_id', $request->tutor_id);
        }

        // Filter by class
        if ($request->has('class_id')) {
            $query->whereHas('classModel', function ($q) use ($request) {
                $q->where('classes.id', $request->class_id);
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);
        $sessions = $query->orderBy('date', 'desc')
                          ->orderBy('start_time', 'desc')
                          ->paginate($perPage);

        // Add attendance statistics for each session
        $sessions->getCollection()->transform(function ($session) {
            $totalStudents = $session->students->count();
            $attendanceRecords = DB::table('session_student')
                ->where('session_id', $session->id)
                ->get();

            $present = $attendanceRecords->where('attendance_status', 'present')->count();
            $absent = $attendanceRecords->where('attendance_status', 'absent')->count();
            $late = $attendanceRecords->where('attendance_status', 'late')->count();

            $session->attendance_stats = [
                'total_students' => $totalStudents,
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'attendance_rate' => $totalStudents > 0 ? round(($present / $totalStudents) * 100, 2) : 0,
            ];

            // Ensure classModel is loaded and add class_name for easier access
            if ($session->classModel) {
                $session->class_name = $session->classModel->name;
            } elseif ($session->class_id) {
                // Fallback: load class if not already loaded
                $class = ClassModel::find($session->class_id);
                if ($class) {
                    $session->class_name = $class->name;
                    $session->classModel = $class;
                }
            }

            // Ensure teacher user is loaded and add tutor_name
            if ($session->teacher && $session->teacher->user) {
                $session->tutor_name = $session->teacher->user->name;
            }

            return $session;
        });

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    public function show(Request $request, $id)
    {
        $session = TutoringSession::with([
            'teacher.user',
            'students.user',
            'classModel'
        ])->findOrFail($id);

        if (!$session->attendance_marked) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance not marked for this session',
            ], 404);
        }

        // Get detailed attendance records
        $attendanceRecords = DB::table('session_student')
            ->where('session_id', $session->id)
            ->join('students', 'session_student.student_id', '=', 'students.id')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->select(
                'session_student.id',
                'session_student.student_id',
                'session_student.session_id',
                'session_student.attendance_status',
                'students.id as student_id',
                'users.name as student_name',
                'users.email as student_email'
            )
            ->get();

        $session->attendance_records = $attendanceRecords;

        return response()->json([
            'success' => true,
            'data' => $session,
        ]);
    }

    public function update(Request $request, $id)
    {
        $session = TutoringSession::findOrFail($id);

        $validated = $request->validate([
            'attendance_records' => 'required|array',
            'attendance_records.*.student_id' => 'required|exists:students,id',
            'attendance_records.*.attendance_status' => 'required|in:present,absent,late',
            'attendance_records.*.notes' => 'nullable|string|max:500',
        ]);

        // Update attendance records
        foreach ($validated['attendance_records'] as $record) {
            $updateData = [
                'attendance_status' => $record['attendance_status'],
                'updated_at' => now(),
            ];
            
            // Only add notes if the column exists (check via schema or migration)
            // For now, we'll skip notes since the column doesn't exist
            // If you add notes column later, uncomment the line below:
            // $updateData['notes'] = $record['notes'] ?? null;
            
            DB::table('session_student')
                ->where('session_id', $session->id)
                ->where('student_id', $record['student_id'])
                ->update($updateData);
        }

        // Mark attendance as marked
        $session->update(['attendance_marked' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Attendance updated successfully',
            'data' => $session->load(['teacher.user', 'students.user']),
        ]);
    }

    public function studentAttendance(Request $request)
    {
        $query = DB::table('session_student')
            ->join('tutoring_sessions', 'session_student.session_id', '=', 'tutoring_sessions.id')
            ->join('students', 'session_student.student_id', '=', 'students.id')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->leftJoin('classes', 'tutoring_sessions.class_id', '=', 'classes.id')
            ->leftJoin('tutors', 'tutoring_sessions.teacher_id', '=', 'tutors.id')
            ->leftJoin('users as tutor_users', 'tutors.user_id', '=', 'tutor_users.id')
            ->where('tutoring_sessions.attendance_marked', true)
            ->select(
                'session_student.id',
                'session_student.student_id',
                'session_student.attendance_status',
                'session_student.session_id',
                'users.name as student_name',
                'users.email as student_email',
                'tutoring_sessions.date as session_date',
                'tutoring_sessions.start_time as session_time',
                'tutoring_sessions.end_time',
                'tutoring_sessions.location',
                'classes.name as class_name',
                'classes.code as class_code',
                'tutor_users.name as tutor_name',
                'tutor_users.email as tutor_email'
            );

        // Search filter
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('users.name', 'like', "%{$search}%")
                  ->orWhere('classes.name', 'like', "%{$search}%")
                  ->orWhere('tutor_users.name', 'like', "%{$search}%");
            });
        }

        // Filter by date
        if ($request->has('date_from')) {
            $query->where('tutoring_sessions.date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('tutoring_sessions.date', '<=', $request->date_to);
        }

        // Filter by class
        if ($request->has('class_id')) {
            $query->where('tutoring_sessions.class_id', $request->class_id);
        }

        // Filter by tutor
        if ($request->has('tutor_id')) {
            $query->where('tutoring_sessions.teacher_id', $request->tutor_id);
        }

        // Filter by attendance status
        if ($request->has('attendance_status')) {
            $query->where('session_student.attendance_status', $request->attendance_status);
        }

        $perPage = $request->get('per_page', 50);
        $records = $query->orderBy('tutoring_sessions.date', 'desc')
                        ->orderBy('tutoring_sessions.start_time', 'desc')
                        ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }

    public function approveTimesheet(Request $request)
    {
        $validated = $request->validate([
            'tutor_id' => 'required|exists:tutors,id',
            'week_ending' => 'required|date',
        ]);

        $tutorId = $validated['tutor_id'];
        $weekEnding = new \DateTime($validated['week_ending']);
        $weekStart = clone $weekEnding;
        $weekStart->modify('-6 days'); // Get the start of the week (7 days before ending)

        // Debug: Log the date range
        \Log::info('Timesheet approval request', [
            'tutor_id' => $tutorId,
            'week_ending' => $weekEnding->format('Y-m-d'),
            'week_start' => $weekStart->format('Y-m-d'),
        ]);

        // First, check if there are any sessions for this tutor in this period (without filters)
        $allSessions = TutoringSession::where('teacher_id', $tutorId)
            ->where('date', '>=', $weekStart->format('Y-m-d'))
            ->where('date', '<=', $weekEnding->format('Y-m-d'))
            ->get();

        \Log::info('All sessions in period', [
            'count' => $allSessions->count(),
            'sessions' => $allSessions->map(fn($s) => [
                'id' => $s->id,
                'date' => $s->date,
                'attendance_marked' => $s->attendance_marked,
                'ready_for_invoicing' => $s->ready_for_invoicing,
            ])->toArray(),
        ]);

        // Get all sessions for this tutor in this week that have attendance marked
        $sessions = TutoringSession::where('teacher_id', $tutorId)
            ->where('attendance_marked', true)
            ->where('date', '>=', $weekStart->format('Y-m-d'))
            ->where('date', '<=', $weekEnding->format('Y-m-d'))
            ->where('ready_for_invoicing', false)
            ->with(['teacher.user', 'classModel'])
            ->get();

        if ($sessions->isEmpty()) {
            // Provide more detailed error message
            $hasSessions = $allSessions->isNotEmpty();
            $hasAttendanceMarked = TutoringSession::where('teacher_id', $tutorId)
                ->where('date', '>=', $weekStart->format('Y-m-d'))
                ->where('date', '<=', $weekEnding->format('Y-m-d'))
                ->where('attendance_marked', true)
                ->exists();
            $allReadyForInvoicing = TutoringSession::where('teacher_id', $tutorId)
                ->where('date', '>=', $weekStart->format('Y-m-d'))
                ->where('date', '<=', $weekEnding->format('Y-m-d'))
                ->where('attendance_marked', true)
                ->where('ready_for_invoicing', false)
                ->count() === 0 && $hasAttendanceMarked;

            $message = 'No sessions found for this timesheet period.';
            if (!$hasSessions) {
                $message .= ' No sessions exist for this tutor in this date range.';
            } elseif (!$hasAttendanceMarked) {
                $message .= ' Sessions exist but attendance has not been marked.';
            } elseif ($allReadyForInvoicing) {
                $message .= ' All sessions in this period have already been invoiced.';
            }

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 404);
        }

        // Calculate total hours and amount
        $totalHours = 0;
        $onlineHours = 0;
        $offlineHours = 0;
        $tutor = $sessions->first()->teacher;
        $hourlyRate = floatval($tutor->hourly_rate ?? 100);

        foreach ($sessions as $session) {
            $start = new \DateTime($session->date->format('Y-m-d') . ' ' . $session->start_time);
            $end = new \DateTime($session->date->format('Y-m-d') . ' ' . $session->end_time);
            $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
            
            $totalHours += $hours;
            if (!$session->location || strtolower($session->location) === 'online' || strtolower($session->location) === 'home') {
                $onlineHours += $hours;
            } else {
                $offlineHours += $hours;
            }
        }

        $totalAmount = $totalHours * $hourlyRate;

        // Mark all sessions as ready for invoicing
        TutoringSession::whereIn('id', $sessions->pluck('id'))
            ->update(['ready_for_invoicing' => true]);

        // Generate invoice number
        $invoiceNumber = 'INV-TUTOR-' . $tutorId . '-' . date('Ymd') . '-' . str_pad(Invoice::where('tutor_id', $tutorId)->count() + 1, 4, '0', STR_PAD_LEFT);

        // Create invoice
        $invoice = \App\Models\Invoice::create([
            'invoice_number' => $invoiceNumber,
            'tutor_id' => $tutorId,
            'amount' => $totalAmount,
            'currency' => 'USD',
            'status' => 'pending',
            'issue_date' => now(),
            'due_date' => now()->addDays(30),
            'period_start' => $weekStart->format('Y-m-d'),
            'period_end' => $weekEnding->format('Y-m-d'),
            'description' => "Tutor timesheet for week ending {$weekEnding->format('Y-m-d')}",
        ]);

        // Create invoice items
        $invoice->items()->create([
            'description' => "Tutoring hours: {$totalHours}h (Online: {$onlineHours}h, Offline: {$offlineHours}h)",
            'amount' => $totalAmount,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Timesheet approved and invoice generated',
            'data' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'status' => 'approved',
                'total_hours' => $totalHours,
                'amount' => $totalAmount,
            ],
        ]);
    }
}

