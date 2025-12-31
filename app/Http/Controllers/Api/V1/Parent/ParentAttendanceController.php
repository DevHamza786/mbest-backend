<?php

namespace App\Http\Controllers\Api\V1\Parent;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TutoringSession;
use App\Models\ParentModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParentAttendanceController extends Controller
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
        ->where('attendance_marked', true)
        ->with(['teacher.user', 'classModel']);

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
            $query->whereHas('students', function ($q) use ($child, $request) {
                $q->where('students.id', $child->id)
                  ->where('session_student.attendance_status', $request->attendance_status);
            });
        }

        $perPage = $request->get('per_page', 15);
        $sessions = $query->orderBy('date', 'desc')
                          ->orderBy('start_time', 'desc')
                          ->paginate($perPage);

        // Add attendance status for each session
        $sessions->getCollection()->transform(function ($session) use ($child) {
            $attendanceRecord = DB::table('session_student')
                ->where('session_id', $session->id)
                ->where('student_id', $child->id)
                ->first();

            $session->attendance_status = $attendanceRecord->attendance_status ?? null;
            $session->attendance_notes = $attendanceRecord->notes ?? null;

            return $session;
        });

        // Calculate statistics
        $totalSessions = TutoringSession::whereHas('students', function ($q) use ($child) {
            $q->where('students.id', $child->id);
        })
        ->where('attendance_marked', true)
        ->count();

        $presentSessions = DB::table('session_student')
            ->join('tutoring_sessions', 'session_student.session_id', '=', 'tutoring_sessions.id')
            ->where('session_student.student_id', $child->id)
            ->where('session_student.attendance_status', 'present')
            ->where('tutoring_sessions.attendance_marked', true)
            ->count();

        $absentSessions = DB::table('session_student')
            ->join('tutoring_sessions', 'session_student.session_id', '=', 'tutoring_sessions.id')
            ->where('session_student.student_id', $child->id)
            ->where('session_student.attendance_status', 'absent')
            ->where('tutoring_sessions.attendance_marked', true)
            ->count();

        $lateSessions = DB::table('session_student')
            ->join('tutoring_sessions', 'session_student.session_id', '=', 'tutoring_sessions.id')
            ->where('session_student.student_id', $child->id)
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

