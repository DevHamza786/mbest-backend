<?php

namespace App\Http\Controllers\Api\V1\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Tutor;
use App\Models\TutoringSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TutorAttendanceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $query = TutoringSession::where('teacher_id', $tutor->id)
            ->where('attendance_marked', true)
            ->with(['students.user']);

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        // Filter by class (through students)
        if ($request->has('class_id')) {
            $query->whereHas('students', function ($q) use ($request) {
                $q->whereHas('classes', function ($q2) use ($request) {
                    $q2->where('classes.id', $request->class_id);
                });
            });
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 10);
        $sessions = $query->orderBy('date', 'desc')
                          ->orderBy('start_time', 'desc')
                          ->paginate($perPage);

        // Add attendance statistics
        $sessions->getCollection()->transform(function ($session) {
            $attendanceRecords = DB::table('session_student')
                ->where('session_id', $session->id)
                ->get();

            $totalStudents = $session->students->count();
            $present = $attendanceRecords->where('attendance_status', 'present')->count();
            $absent = $attendanceRecords->where('attendance_status', 'absent')->count();
            $late = $attendanceRecords->where('attendance_status', 'late')->count();

            $session->attendance_summary = [
                'total_students' => $totalStudents,
                'present' => $present,
                'absent' => $absent,
                'late' => $late,
                'attendance_rate' => $totalStudents > 0 ? round(($present / $totalStudents) * 100, 2) : 0,
            ];

            return $session;
        });

        return response()->json([
            'success' => true,
            'data' => $sessions,
        ]);
    }

    public function records(Request $request)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $query = DB::table('session_student')
            ->join('tutoring_sessions', 'session_student.session_id', '=', 'tutoring_sessions.id')
            ->join('students', 'session_student.student_id', '=', 'students.id')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->where('tutoring_sessions.teacher_id', $tutor->id)
            ->where('tutoring_sessions.attendance_marked', true)
            ->select(
                'session_student.id as record_id',
                'tutoring_sessions.id as session_id',
                'tutoring_sessions.date',
                'tutoring_sessions.start_time',
                'tutoring_sessions.end_time',
                'tutoring_sessions.subject',
                'tutoring_sessions.location',
                'students.id as student_id',
                'users.name as student_name',
                'users.email as student_email',
                'session_student.attendance_status',
                'session_student.created_at',
                'session_student.updated_at'
            )
            ->groupBy(
                'session_student.id',
                'tutoring_sessions.id',
                'tutoring_sessions.date',
                'tutoring_sessions.start_time',
                'tutoring_sessions.end_time',
                'tutoring_sessions.subject',
                'tutoring_sessions.location',
                'students.id',
                'users.name',
                'users.email',
                'session_student.attendance_status',
                'session_student.created_at',
                'session_student.updated_at'
            );

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('tutoring_sessions.date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('tutoring_sessions.date', '<=', $request->date_to);
        }

        // Filter by student
        if ($request->has('student_id')) {
            $query->where('students.id', $request->student_id);
        }

        // Filter by attendance status
        if ($request->has('attendance_status')) {
            $query->where('session_student.attendance_status', $request->attendance_status);
        }

        // Filter by class (through students)
        if ($request->has('class_id')) {
            $query->whereExists(function ($q) use ($request) {
                $q->select(DB::raw(1))
                  ->from('class_student')
                  ->whereColumn('class_student.student_id', 'students.id')
                  ->where('class_student.class_id', $request->class_id);
            });
        }

        $perPage = $request->get('per_page', 10);
        $records = $query->orderBy('tutoring_sessions.date', 'desc')
                         ->orderBy('tutoring_sessions.start_time', 'desc')
                         ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $records,
        ]);
    }
}

