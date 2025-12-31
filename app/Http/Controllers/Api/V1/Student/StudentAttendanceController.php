<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TutoringSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        $query = TutoringSession::whereHas('students', function ($q) use ($student) {
            $q->where('students.id', $student->id);
        })
        ->with(['teacher.user', 'classModel']);

        // If requesting lessons for a specific class, show upcoming sessions only and don't filter by attendance_marked
        // Otherwise, only show sessions with attendance marked
        if ($request->has('class_id')) {
            // For class lessons, show only upcoming (date >= today)
            $today = now()->format('Y-m-d');
            $query->where('date', '>=', $today);
        } else {
            // For attendance records, only show sessions with attendance marked
            $query->where('attendance_marked', true);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        // Filter by class
        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        // Filter by attendance status
        if ($request->has('attendance_status')) {
            $query->whereHas('students', function ($q) use ($student, $request) {
                $q->where('students.id', $student->id)
                  ->where('session_student.attendance_status', $request->attendance_status);
            });
        }

        $perPage = $request->get('per_page', 10);
        // For class lessons, order by date ascending (upcoming first)
        // For attendance records, order by date descending (recent first)
        if ($request->has('class_id')) {
            $sessions = $query->orderBy('date', 'asc')
                              ->orderBy('start_time', 'asc')
                              ->paginate($perPage);
        } else {
            $sessions = $query->orderBy('date', 'desc')
                              ->orderBy('start_time', 'desc')
                              ->paginate($perPage);
        }

        // Add attendance status for each session
        $sessions->getCollection()->transform(function ($session) use ($student) {
            $attendanceRecord = DB::table('session_student')
                ->where('session_id', $session->id)
                ->where('student_id', $student->id)
                ->first();

            $session->attendance_status = $attendanceRecord->attendance_status ?? null;
            $session->attendance_notes = $attendanceRecord->notes ?? null;

            return $session;
        });

        // Calculate statistics
        $totalSessions = TutoringSession::whereHas('students', function ($q) use ($student) {
            $q->where('students.id', $student->id);
        })
        ->where('attendance_marked', true)
        ->count();

        $presentSessions = DB::table('session_student')
            ->join('tutoring_sessions', 'session_student.session_id', '=', 'tutoring_sessions.id')
            ->where('session_student.student_id', $student->id)
            ->where('session_student.attendance_status', 'present')
            ->where('tutoring_sessions.attendance_marked', true)
            ->count();

        $absentSessions = DB::table('session_student')
            ->join('tutoring_sessions', 'session_student.session_id', '=', 'tutoring_sessions.id')
            ->where('session_student.student_id', $student->id)
            ->where('session_student.attendance_status', 'absent')
            ->where('tutoring_sessions.attendance_marked', true)
            ->count();

        $lateSessions = DB::table('session_student')
            ->join('tutoring_sessions', 'session_student.session_id', '=', 'tutoring_sessions.id')
            ->where('session_student.student_id', $student->id)
            ->where('session_student.attendance_status', 'late')
            ->where('tutoring_sessions.attendance_marked', true)
            ->count();

        $attendanceRate = $totalSessions > 0 
            ? round(($presentSessions / $totalSessions) * 100, 2) 
            : 0;

        return response()->json([
            'success' => true,
            'data' => $sessions,
            'stats' => [
                'total_sessions' => $totalSessions,
                'present' => $presentSessions,
                'absent' => $absentSessions,
                'late' => $lateSessions,
                'attendance_rate' => $attendanceRate,
            ],
        ]);
    }
}

