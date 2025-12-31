<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ClassModel;
use App\Models\Invoice;
use App\Models\Assignment;
use App\Models\TutoringSession;
use App\Models\Resource;
use App\Models\Grade;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->get('date_from', now()->startOfMonth()->toDateString());
        $dateTo = $request->get('date_to', now()->endOfMonth()->toDateString());

        $analytics = [
            'overview' => [
                'total_students' => User::where('role', 'student')->count(),
                'total_tutors' => User::where('role', 'tutor')->count(),
                'total_classes' => ClassModel::where('status', 'active')->count(),
                'total_invoices' => Invoice::whereBetween('issue_date', [$dateFrom, $dateTo])->count(),
            ],
            'revenue' => [
                'total_revenue' => Invoice::where('status', 'paid')
                    ->whereBetween('paid_date', [$dateFrom, $dateTo])
                    ->sum('amount'),
                'pending_revenue' => Invoice::where('status', 'pending')
                    ->whereBetween('issue_date', [$dateFrom, $dateTo])
                    ->sum('amount'),
                'overdue_revenue' => Invoice::where('status', 'overdue')
                    ->sum('amount'),
            ],
            'sessions' => [
                'total_sessions' => TutoringSession::whereBetween('date', [$dateFrom, $dateTo])->count(),
                'completed_sessions' => TutoringSession::where('status', 'completed')
                    ->whereBetween('date', [$dateFrom, $dateTo])
                    ->count(),
                'cancelled_sessions' => TutoringSession::where('status', 'cancelled')
                    ->whereBetween('date', [$dateFrom, $dateTo])
                    ->count(),
            ],
            'assignments' => [
                'total_assignments' => Assignment::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
                'published_assignments' => Assignment::where('status', 'published')
                    ->whereBetween('created_at', [$dateFrom, $dateTo])
                    ->count(),
            ],
            'enrollment_trends' => $this->getEnrollmentTrends($dateFrom, $dateTo),
            'course_distribution' => $this->getCourseDistribution(),
            'revenue_trends' => $this->getRevenueTrends($dateFrom, $dateTo),
            'top_courses' => $this->getTopPerformingCourses(),
            'performance_metrics' => $this->getPerformanceMetrics($dateFrom, $dateTo),
        ];

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    private function getEnrollmentTrends($dateFrom, $dateTo)
    {
        // Get monthly enrollment data for the last 6 months
        $months = [];
        $startDate = \Carbon\Carbon::parse($dateFrom);
        $endDate = \Carbon\Carbon::parse($dateTo);
        
        // Get last 6 months from end date
        $current = $endDate->copy()->startOfMonth();
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = $current->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $current->copy()->subMonths($i)->endOfMonth();
            
            $enrollments = DB::table('class_student')
                ->whereBetween('created_at', [$monthStart, $monthEnd])
                ->count();
            
            $revenue = Invoice::where('status', 'paid')
                ->whereBetween('paid_date', [$monthStart, $monthEnd])
                ->sum('amount');
            
            $months[] = [
                'month' => $monthStart->format('M'),
                'students' => $enrollments,
                'revenue' => (float) $revenue,
            ];
        }
        
        return $months;
    }

    private function getCourseDistribution()
    {
        // Get distribution of students across course categories
        $distribution = DB::table('class_student')
            ->join('classes', 'class_student.class_id', '=', 'classes.id')
            ->select('classes.category', DB::raw('count(*) as count'))
            ->groupBy('classes.category')
            ->get();
        
        $total = $distribution->sum('count');
        
        $categories = [
            'Computer Science' => 0,
            'Business' => 0,
            'Mathematics' => 0,
            'Languages' => 0,
            'Arts' => 0,
        ];
        
        foreach ($distribution as $item) {
            $category = $item->category ?? 'Other';
            if (isset($categories[$category])) {
                $categories[$category] = $total > 0 ? round(($item->count / $total) * 100) : 0;
            }
        }
        
        $result = [];
        $colors = [
            'Computer Science' => 'hsl(var(--primary))',
            'Business' => 'hsl(var(--secondary))',
            'Mathematics' => 'hsl(var(--accent))',
            'Languages' => 'hsl(var(--muted))',
            'Arts' => 'hsl(var(--border))',
        ];
        
        foreach ($categories as $name => $value) {
            $result[] = [
                'name' => $name,
                'value' => $value,
                'color' => $colors[$name] ?? 'hsl(var(--muted))',
            ];
        }
        
        return $result;
    }

    private function getRevenueTrends($dateFrom, $dateTo)
    {
        // Get monthly revenue data for the last 6 months
        $months = [];
        $startDate = \Carbon\Carbon::parse($dateFrom);
        $endDate = \Carbon\Carbon::parse($dateTo);
        
        $current = $endDate->copy()->startOfMonth();
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = $current->copy()->subMonths($i)->startOfMonth();
            $monthEnd = $current->copy()->subMonths($i)->endOfMonth();
            
            $revenue = Invoice::where('status', 'paid')
                ->whereBetween('paid_date', [$monthStart, $monthEnd])
                ->sum('amount');
            
            $months[] = [
                'month' => $monthStart->format('M'),
                'revenue' => (float) $revenue,
            ];
        }
        
        return $months;
    }

    private function getTopPerformingCourses()
    {
        // Get top performing courses by completion rate
        $courses = DB::table('classes')
            ->leftJoin('class_student', 'classes.id', '=', 'class_student.class_id')
            ->leftJoin('assignments', 'classes.id', '=', 'assignments.class_id')
            ->leftJoin('assignment_submissions', function($join) {
                $join->on('assignments.id', '=', 'assignment_submissions.assignment_id')
                     ->where('assignment_submissions.status', '=', 'submitted');
            })
            ->select(
                'classes.id',
                'classes.name',
                DB::raw('COUNT(DISTINCT class_student.student_id) as student_count'),
                DB::raw('COUNT(DISTINCT assignment_submissions.id) as submissions_count'),
                DB::raw('COUNT(DISTINCT assignments.id) as assignments_count')
            )
            ->groupBy('classes.id', 'classes.name')
            ->having('student_count', '>', 0)
            ->orderBy('submissions_count', 'desc')
            ->limit(3)
            ->get();
        
        $result = [];
        foreach ($courses as $course) {
            $completionRate = $course->assignments_count > 0 
                ? round(($course->submissions_count / ($course->student_count * $course->assignments_count)) * 100)
                : 0;
            
            $result[] = [
                'name' => $course->name,
                'students' => $course->student_count,
                'rate' => min($completionRate, 100),
            ];
        }
        
        return $result;
    }

    private function getPerformanceMetrics($dateFrom, $dateTo)
    {
        // Class Completion Rate: Percentage of assignments that have been submitted
        $totalAssignments = Assignment::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $totalSubmissions = DB::table('assignment_submissions')
            ->where('status', 'submitted')
            ->whereBetween('submitted_at', [$dateFrom, $dateTo])
            ->count();
        
        // Calculate expected submissions: total assignments * average students per class
        // For simplicity, use actual submissions vs total assignments
        $classCompletionRate = $totalAssignments > 0 
            ? round(($totalSubmissions / $totalAssignments) * 100) 
            : 0;
        
        // Cap at 100%
        $classCompletionRate = min($classCompletionRate, 100);

        // Session Completion Rate: Already calculated in sessions data
        $totalSessions = TutoringSession::whereBetween('date', [$dateFrom, $dateTo])->count();
        $completedSessions = TutoringSession::where('status', 'completed')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->count();
        $sessionCompletionRate = $totalSessions > 0 
            ? round(($completedSessions / $totalSessions) * 100) 
            : 0;

        // Assignment Publishing Rate: Already calculated in assignments data
        $totalAssignments = Assignment::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $publishedAssignments = Assignment::where('status', 'published')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();
        $assignmentPublishingRate = $totalAssignments > 0 
            ? round(($publishedAssignments / $totalAssignments) * 100) 
            : 0;

        // Resource Usage: Percentage of resources that have been downloaded
        $totalResources = Resource::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $usedResources = Resource::where('downloads', '>', 0)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->count();
        $resourceUsageRate = $totalResources > 0 
            ? round(($usedResources / $totalResources) * 100) 
            : 0;

        // Student Satisfaction: Based on average grades (higher grades = higher satisfaction)
        $averageGrade = Grade::whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw('AVG((grade / max_grade) * 100) as avg_percentage')
            ->first();
        $studentSatisfaction = $averageGrade && $averageGrade->avg_percentage 
            ? round($averageGrade->avg_percentage) 
            : 0;

        // Tutor Performance: Based on session completion rate and student grades
        $tutorSessions = DB::table('tutoring_sessions')
            ->whereBetween('date', [$dateFrom, $dateTo])
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed
            ')
            ->first();
        
        $tutorSessionRate = $tutorSessions && $tutorSessions->total > 0
            ? round(($tutorSessions->completed / $tutorSessions->total) * 100)
            : 0;

        // Combine tutor performance metrics (50% session completion, 50% student grades)
        $tutorPerformance = round(($tutorSessionRate * 0.5) + ($studentSatisfaction * 0.5));

        return [
            [
                'metric' => 'Class Completion Rate',
                'value' => $classCompletionRate,
                'target' => 85,
            ],
            [
                'metric' => 'Session Completion',
                'value' => $sessionCompletionRate,
                'target' => 80,
            ],
            [
                'metric' => 'Assignment Publishing',
                'value' => $assignmentPublishingRate,
                'target' => 90,
            ],
            [
                'metric' => 'Resource Usage',
                'value' => $resourceUsageRate,
                'target' => 80,
            ],
            [
                'metric' => 'Student Satisfaction',
                'value' => $studentSatisfaction,
                'target' => 90,
            ],
            [
                'metric' => 'Tutor Performance',
                'value' => $tutorPerformance,
                'target' => 88,
            ],
        ];
    }
}

