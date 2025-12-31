<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ClassModel;
use App\Models\Invoice;
use App\Models\TutoringSession;
use App\Models\Assignment;
use App\Models\Notification;
use App\Models\Student;
use App\Models\Message;
use Illuminate\Http\Request;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $stats = [
            'total_students' => User::where('role', 'student')->count(),
            'total_tutors' => User::where('role', 'tutor')->count(),
            'total_classes' => ClassModel::where('status', 'active')->count(),
            'monthly_revenue' => Invoice::where('status', 'paid')
                ->whereMonth('paid_date', now()->month)
                ->sum('amount'),
        ];

        // Recent Activities
        $recentActivities = $this->getRecentActivities();

        // Enrollment Progress
        $enrollmentProgress = $this->getEnrollmentProgress();

        // System Alerts
        $systemAlerts = $this->getSystemAlerts();

        // System Status
        $systemStatus = $this->getSystemStatus();

        return response()->json([
            'success' => true,
            'data' => array_merge($stats, [
                'recent_activities' => $recentActivities,
                'enrollment_progress' => $enrollmentProgress,
                'system_alerts' => $systemAlerts,
                'system_status' => $systemStatus,
            ]),
        ]);
    }

    private function getRecentActivities()
    {
        $activities = [];

        // Recent enrollments (students joining classes)
        $recentEnrollments = \DB::table('class_student')
            ->join('users', 'class_student.student_id', '=', 'users.id')
            ->join('classes', 'class_student.class_id', '=', 'classes.id')
            ->where('class_student.created_at', '>=', now()->subDays(7))
            ->select('class_student.id as enrollment_id', 'users.name as student_name', 'classes.name as class_name', 'class_student.created_at')
            ->orderBy('class_student.created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentEnrollments as $enrollment) {
            $activities[] = [
                'id' => 'enrollment_' . $enrollment->enrollment_id,
                'type' => 'enrollment',
                'message' => $enrollment->student_name . ' enrolled in ' . $enrollment->class_name,
                'time' => Carbon::parse($enrollment->created_at)->diffForHumans(),
                'status' => 'success',
                'created_at' => $enrollment->created_at,
            ];
        }

        // Recent payments
        $recentPayments = Invoice::where('status', 'paid')
            ->where('paid_date', '>=', now()->subDays(7))
            ->with(['student.user'])
            ->orderBy('paid_date', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentPayments as $payment) {
            $studentName = $payment->student->user->name ?? 'Unknown';
            $activities[] = [
                'id' => 'payment_' . $payment->id . '_' . strtotime($payment->paid_date),
                'type' => 'payment',
                'message' => 'Payment received from ' . $studentName . ' ($' . number_format($payment->amount, 2) . ')',
                'time' => Carbon::parse($payment->paid_date)->diffForHumans(),
                'status' => 'success',
                'created_at' => $payment->paid_date,
            ];
        }

        // Recent notifications
        $recentNotifications = Notification::where('created_at', '>=', now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentNotifications as $notification) {
            $activities[] = [
                'id' => 'notification_' . $notification->id,
                'type' => 'notification',
                'message' => $notification->title ?? $notification->message,
                'time' => Carbon::parse($notification->created_at)->diffForHumans(),
                'status' => $notification->type === 'warning' ? 'warning' : 'info',
                'created_at' => $notification->created_at,
            ];
        }

        // Recent class updates
        $recentClassUpdates = ClassModel::where('updated_at', '>=', now()->subDays(7))
            ->whereColumn('updated_at', '>', 'created_at')
            ->with(['tutor.user'])
            ->orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        foreach ($recentClassUpdates as $class) {
            $tutorName = $class->tutor->user->name ?? 'Unknown';
            $activities[] = [
                'id' => 'class_update_' . $class->id . '_' . strtotime($class->updated_at),
                'type' => 'class',
                'message' => $tutorName . ' updated ' . $class->name,
                'time' => Carbon::parse($class->updated_at)->diffForHumans(),
                'status' => 'info',
                'created_at' => $class->updated_at,
            ];
        }

        // Sort by created_at and limit to 10 most recent
        usort($activities, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return array_slice($activities, 0, 10);
    }

    private function getEnrollmentProgress()
    {
        // Get current month enrollments
        $currentEnrollments = \DB::table('class_student')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Target enrollments (can be configured, default to 125)
        $targetEnrollments = 125;
        $percentage = $targetEnrollments > 0 ? round(($currentEnrollments / $targetEnrollments) * 100) : 0;

        return [
            'current' => $currentEnrollments,
            'target' => $targetEnrollments,
            'percentage' => min($percentage, 100),
        ];
    }

    private function getSystemAlerts()
    {
        $alerts = [];

        // Unbilled Sessions (completed sessions ready for invoicing)
        $unbilledSessions = TutoringSession::where('status', 'completed')
            ->where('ready_for_invoicing', true)
            ->where('date', '<=', now()->subDays(7)) // Sessions older than 7 days
            ->count();

        if ($unbilledSessions > 0) {
            $alerts[] = [
                'id' => 'unbilled_sessions',
                'type' => 'critical',
                'category' => 'session',
                'title' => 'Unbilled Sessions',
                'description' => 'Sessions have been completed but not yet invoiced',
                'count' => $unbilledSessions,
                'action' => 'Review Sessions',
                'action_url' => '/admin/billing?filter=unbilled',
            ];
        }

        // Missing Lesson Notes
        $missingNotes = TutoringSession::where('status', 'completed')
            ->where(function ($query) {
                $query->whereNull('lesson_note')
                    ->orWhere('lesson_note', '');
            })
            ->count();

        if ($missingNotes > 0) {
            $alerts[] = [
                'id' => 'missing_notes',
                'type' => 'warning',
                'category' => 'session',
                'title' => 'Missing Lesson Notes',
                'description' => 'Completed sessions without lesson notes',
                'count' => $missingNotes,
                'action' => 'Review Sessions',
                'action_url' => '/admin/classes?filter=missing-notes',
            ];
        }

        // Overdue Invoices
        $overdueInvoices = Invoice::where('status', '!=', 'paid')
            ->where('due_date', '<', now())
            ->count();

        if ($overdueInvoices > 0) {
            $alerts[] = [
                'id' => 'overdue_invoices',
                'type' => 'info',
                'category' => 'billing',
                'title' => 'Overdue Invoices',
                'description' => 'Student invoices past due date',
                'count' => $overdueInvoices,
                'action' => 'View Invoices',
                'action_url' => '/admin/billing?filter=overdue',
            ];
        }

        // Conflicting Bookings (sessions at same time for same tutor)
        // Note: tutoring_sessions table uses 'teacher_id' not 'tutor_id'
        $conflictingBookings = \DB::table('tutoring_sessions as ts1')
            ->join('tutoring_sessions as ts2', function ($join) {
                $join->on('ts1.teacher_id', '=', 'ts2.teacher_id')
                    ->whereColumn('ts1.id', '<>', 'ts2.id')
                    ->whereColumn('ts1.date', '=', 'ts2.date')
                    ->where(function ($query) {
                        // Check if ts1 overlaps with ts2: ts1.start < ts2.end AND ts1.end > ts2.start
                        $query->whereRaw('ts1.start_time < ts2.end_time')
                              ->whereRaw('ts1.end_time > ts2.start_time');
                    });
            })
            ->where('ts1.date', '>=', now()->toDateString())
            ->distinct('ts1.id')
            ->count('ts1.id');

        if ($conflictingBookings > 0) {
            $alerts[] = [
                'id' => 'conflicting_bookings',
                'type' => 'warning',
                'category' => 'session',
                'title' => 'Conflicting Bookings',
                'description' => 'Teachers scheduled for multiple sessions at the same time',
                'count' => $conflictingBookings,
                'action' => 'Resolve Conflicts',
                'action_url' => '/admin/calendar?filter=conflicts',
            ];
        }

        // Incomplete Profiles
        $incompleteProfiles = User::where(function ($query) {
            $query->whereNull('phone')
                ->orWhereNull('date_of_birth')
                ->orWhereNull('address');
        })
            ->whereIn('role', ['student', 'tutor'])
            ->count();

        if ($incompleteProfiles > 0) {
            $alerts[] = [
                'id' => 'incomplete_profiles',
                'type' => 'info',
                'category' => 'profile',
                'title' => 'Incomplete Profiles',
                'description' => 'Student or teacher profiles with missing information',
                'count' => $incompleteProfiles,
                'action' => 'Review Profiles',
                'action_url' => '/admin/users?filter=incomplete',
            ];
        }

        // Pending Timesheet Approvals (sessions without attendance marked)
        $pendingTimesheets = TutoringSession::where('status', 'completed')
            ->whereDoesntHave('students', function ($query) {
                $query->whereNotNull('attendance_status');
            })
            ->count();

        if ($pendingTimesheets > 0) {
            $alerts[] = [
                'id' => 'pending_timesheets',
                'type' => 'warning',
                'category' => 'attendance',
                'title' => 'Pending Timesheet Approvals',
                'description' => 'Tutor timesheets awaiting approval',
                'count' => $pendingTimesheets,
                'action' => 'Review Timesheets',
                'action_url' => '/admin/hours?filter=pending',
            ];
        }

        return $alerts;
    }

    private function getSystemStatus()
    {
        // These can be static or calculated based on system health
        return [
            'server_health' => 'Excellent',
            'database_performance' => 'Good',
            'backup_status' => 'Scheduled',
        ];
    }
}

