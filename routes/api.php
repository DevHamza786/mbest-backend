<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\Admin\AdminDashboardController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;
use App\Http\Controllers\Api\V1\Admin\AdminClassController;
use App\Http\Controllers\Api\V1\Admin\AdminBillingController;
use App\Http\Controllers\Api\V1\Admin\AdminAnalyticsController;
use App\Http\Controllers\Api\V1\Tutor\TutorDashboardController;
use App\Http\Controllers\Api\V1\Tutor\TutorClassController;
use App\Http\Controllers\Api\V1\Tutor\TutorSessionController;
use App\Http\Controllers\Api\V1\Tutor\TutorAssignmentController;
use App\Http\Controllers\Api\V1\Tutor\TutorAvailabilityController;
use App\Http\Controllers\Api\V1\Tutor\TutorStudentController;
use App\Http\Controllers\Api\V1\Tutor\TutorAttendanceController;
use App\Http\Controllers\Api\V1\Tutor\TutorHoursController;
use App\Http\Controllers\Api\V1\Tutor\TutorLessonHistoryController;
use App\Http\Controllers\Api\V1\Tutor\TutorLessonRequestController;
use App\Http\Controllers\Api\V1\Admin\AdminCalendarController;
use App\Http\Controllers\Api\V1\Admin\AdminAttendanceController;
use App\Http\Controllers\Api\V1\Student\StudentDashboardController;
use App\Http\Controllers\Api\V1\Student\StudentClassController;
use App\Http\Controllers\Api\V1\Student\StudentAssignmentController;
use App\Http\Controllers\Api\V1\Student\StudentGradeController;
use App\Http\Controllers\Api\V1\Student\StudentAttendanceController;
use App\Http\Controllers\Api\V1\Student\StudentQuestionController;
use App\Http\Controllers\Api\V1\Tutor\TutorQuestionController;
use App\Http\Controllers\Api\V1\Parent\ParentDashboardController;
use App\Http\Controllers\Api\V1\Parent\ParentChildController;
use App\Http\Controllers\Api\V1\Parent\ParentClassController;
use App\Http\Controllers\Api\V1\Parent\ParentBillingController;
use App\Http\Controllers\Api\V1\Parent\ParentAssignmentController;
use App\Http\Controllers\Api\V1\Parent\ParentGradeController;
use App\Http\Controllers\Api\V1\Parent\ParentAttendanceController;
use App\Http\Controllers\Api\V1\Parent\ParentLessonHistoryController;
use App\Http\Controllers\Api\V1\Common\MessageController;
use App\Http\Controllers\Api\V1\Common\NotificationController;
use App\Http\Controllers\Api\V1\Common\ResourceController;
use App\Http\Controllers\Api\V1\Common\ResourceRequestController;
use App\Http\Controllers\Api\V1\Common\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Public routes
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        // Authentication routes
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);

        // Admin routes
        Route::prefix('admin')->middleware('role:admin')->group(function () {
            Route::get('/dashboard', [AdminDashboardController::class, 'index']);
            
            // Users management
            Route::get('/users', [AdminUserController::class, 'index']);
            Route::get('/users/stats', [AdminUserController::class, 'stats']);
            Route::post('/users', [AdminUserController::class, 'store']);
            Route::get('/users/{id}', [AdminUserController::class, 'show']);
            Route::put('/users/{id}', [AdminUserController::class, 'update']);
            Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);
            
            // Classes management
            Route::get('/classes', [AdminClassController::class, 'index']);
            Route::post('/classes', [AdminClassController::class, 'store']);
            Route::get('/classes/{id}', [AdminClassController::class, 'show']);
            Route::put('/classes/{id}', [AdminClassController::class, 'update']);
            Route::delete('/classes/{id}', [AdminClassController::class, 'destroy']);
            
            // Billing
            Route::prefix('billing')->group(function () {
                Route::get('/invoices', [AdminBillingController::class, 'index']);
                Route::post('/invoices', [AdminBillingController::class, 'store']);
                Route::get('/invoices/{id}', [AdminBillingController::class, 'show']);
                Route::put('/invoices/{id}', [AdminBillingController::class, 'update']);
            });
            
            // Analytics
            Route::get('/analytics', [AdminAnalyticsController::class, 'index']);
            
            // Calendar
            Route::get('/calendar/sessions', [AdminCalendarController::class, 'index']);
            Route::post('/calendar/sessions', [AdminCalendarController::class, 'store']);
            Route::get('/calendar/sessions/{id}', [AdminCalendarController::class, 'show']);
            Route::put('/calendar/sessions/{id}', [AdminCalendarController::class, 'update']);
            Route::post('/calendar/sessions/{id}/notes', [AdminCalendarController::class, 'addNotes']);
            Route::post('/calendar/sessions/{id}/attendance', [AdminCalendarController::class, 'markAttendance']);
            Route::post('/calendar/sessions/{id}/mark-ready-invoice', [AdminCalendarController::class, 'markReadyForInvoicing']);
            Route::get('/calendar/filter-options', [AdminCalendarController::class, 'filterOptions']);
            
            // Attendance
            Route::get('/attendance', [AdminAttendanceController::class, 'index']);
            Route::get('/attendance/students', [AdminAttendanceController::class, 'studentAttendance']);
            Route::get('/attendance/{id}', [AdminAttendanceController::class, 'show']);
            Route::put('/attendance/{id}', [AdminAttendanceController::class, 'update']);
            Route::post('/attendance/timesheets/approve', [AdminAttendanceController::class, 'approveTimesheet']);
        });

        // Tutor routes
        Route::prefix('tutor')->middleware('role:tutor')->group(function () {
            Route::get('/dashboard', [TutorDashboardController::class, 'index']);
            
            // Classes
            Route::get('/classes', [TutorClassController::class, 'index']);
            Route::get('/classes/{id}', [TutorClassController::class, 'show']);
            Route::get('/classes/{id}/students', [TutorClassController::class, 'students']);
            
            // Sessions
            Route::get('/sessions', [TutorSessionController::class, 'index']);
            Route::post('/sessions', [TutorSessionController::class, 'store']);
            Route::get('/sessions/{id}', [TutorSessionController::class, 'show']);
            Route::put('/sessions/{id}', [TutorSessionController::class, 'update']);
            Route::delete('/sessions/{id}', [TutorSessionController::class, 'destroy']);
            Route::post('/sessions/{id}/notes', [TutorSessionController::class, 'addNotes']);
            Route::post('/sessions/{id}/attendance', [TutorSessionController::class, 'markAttendance']);
            
            // Assignments
            Route::get('/assignments', [TutorAssignmentController::class, 'index']);
            Route::post('/assignments', [TutorAssignmentController::class, 'store']);
            Route::get('/assignments/{id}', [TutorAssignmentController::class, 'show']);
            Route::put('/assignments/{id}', [TutorAssignmentController::class, 'update']);
            Route::delete('/assignments/{id}', [TutorAssignmentController::class, 'destroy']);
            Route::get('/assignments/{id}/submissions', [TutorAssignmentController::class, 'submissions']);
            Route::put('/submissions/{id}/grade', [TutorAssignmentController::class, 'grade']);
            
            // Students
            Route::get('/students', [TutorStudentController::class, 'index']);
            Route::get('/students/recipients', [TutorStudentController::class, 'recipients']);
            Route::get('/students/{id}', [TutorStudentController::class, 'show']);
            Route::get('/students/{id}/grades', [TutorStudentController::class, 'grades']);
            Route::get('/students/{id}/assignments', [TutorStudentController::class, 'assignments']);
            
            // Attendance
            Route::get('/attendance', [TutorAttendanceController::class, 'index']);
            Route::get('/attendance-records', [TutorAttendanceController::class, 'records']);
            
            // Hours & Invoices
            Route::get('/hours', [TutorHoursController::class, 'index']);
            Route::get('/invoices', [TutorHoursController::class, 'invoices']);
            Route::post('/invoices', [TutorHoursController::class, 'createInvoice']);
            
            // Lesson History
            Route::get('/lesson-history', [TutorLessonHistoryController::class, 'index']);
            
            // Availability
            Route::get('/availability', [TutorAvailabilityController::class, 'index']);
            Route::post('/availability', [TutorAvailabilityController::class, 'store']);
            Route::put('/availability/{id}', [TutorAvailabilityController::class, 'update']);
            Route::delete('/availability/{id}', [TutorAvailabilityController::class, 'destroy']);
            
            // Lesson Requests
            Route::get('/lesson-requests', [TutorLessonRequestController::class, 'index']);
            Route::post('/lesson-requests/{id}/approve', [TutorLessonRequestController::class, 'approve']);
            Route::post('/lesson-requests/{id}/decline', [TutorLessonRequestController::class, 'decline']);
            
            // Questions
            Route::get('/questions', [TutorQuestionController::class, 'index']);
            Route::get('/questions/{id}', [TutorQuestionController::class, 'show']);
            Route::post('/questions/{id}/reply', [TutorQuestionController::class, 'reply']);
            Route::put('/questions/{id}/status', [TutorQuestionController::class, 'updateStatus']);
        });

        // Student routes
        Route::prefix('student')->middleware('role:student')->group(function () {
            Route::get('/dashboard', [StudentDashboardController::class, 'index']);
            
            // Classes
            Route::get('/classes', [StudentClassController::class, 'index']);
            Route::get('/classes/{id}', [StudentClassController::class, 'show']);
            Route::post('/classes/{id}/enroll', [StudentClassController::class, 'enroll']);
            Route::post('/classes/{id}/unenroll', [StudentClassController::class, 'unenroll']);
            
            // Assignments
            Route::get('/assignments', [StudentAssignmentController::class, 'index']);
            Route::get('/assignments/{id}', [StudentAssignmentController::class, 'show']);
            Route::post('/assignments/{id}/submit', [StudentAssignmentController::class, 'submit']);
            Route::post('/assignments/{id}/submit/{submissionId}', [StudentAssignmentController::class, 'submit']); // Update submission
            Route::get('/assignments/{id}/submission', [StudentAssignmentController::class, 'getSubmission']);
            
            // Grades
            Route::get('/grades', [StudentGradeController::class, 'index']);
            Route::get('/grades/{id}', [StudentGradeController::class, 'show']);
            
            // Attendance
            Route::get('/attendance', [StudentAttendanceController::class, 'index']);
            
            // Questions
            Route::get('/questions', [StudentQuestionController::class, 'index']);
            Route::get('/questions/{id}', [StudentQuestionController::class, 'show']);
            Route::post('/questions', [StudentQuestionController::class, 'store']);
        });

        // Parent routes
        Route::prefix('parent')->middleware('role:parent')->group(function () {
            Route::get('/dashboard', [ParentDashboardController::class, 'index']);
            
            // Children
            Route::get('/children', [ParentChildController::class, 'index']);
            Route::get('/children/{id}/stats', [ParentChildController::class, 'stats']);
            
            // Classes
            Route::get('/children/{id}/classes', [ParentClassController::class, 'index']);
            Route::get('/children/{childId}/classes/{classId}', [ParentClassController::class, 'show']);
            
            // Assignments
            Route::get('/children/{id}/assignments', [ParentAssignmentController::class, 'index']);
            Route::get('/children/{childId}/assignments/{assignmentId}', [ParentAssignmentController::class, 'show']);
            
            // Grades
            Route::get('/children/{id}/grades', [ParentGradeController::class, 'index']);
            
            // Attendance
            Route::get('/children/{id}/attendance', [ParentAttendanceController::class, 'index']);
            
            // Lesson History
            Route::get('/children/{id}/sessions', [ParentLessonHistoryController::class, 'index']);
            Route::get('/children/{childId}/sessions/{sessionId}', [ParentLessonHistoryController::class, 'show']);
            
            // Billing
            Route::prefix('billing')->group(function () {
                Route::get('/invoices', [ParentBillingController::class, 'index']);
                Route::get('/invoices/{id}', [ParentBillingController::class, 'show']);
                Route::get('/invoices/{id}/pdf', [ParentBillingController::class, 'downloadPdf']);
            });
        });

        // Common routes (available to all authenticated users)
        // Messages
        Route::get('/messages', [MessageController::class, 'index']);
        Route::post('/messages', [MessageController::class, 'store']);
        Route::get('/messages/threads', [MessageController::class, 'threads']);
        Route::get('/messages/{id}', [MessageController::class, 'show']);
        Route::put('/messages/{id}/read', [MessageController::class, 'markAsRead']);
        Route::delete('/messages/{id}', [MessageController::class, 'destroy']);
        
        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::get('/notifications/{id}', [NotificationController::class, 'show']);
        Route::put('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);
        
        // Resources
        Route::get('/resources', [ResourceController::class, 'index']);
        Route::post('/resources', [ResourceController::class, 'store']);
        Route::get('/resources/{id}', [ResourceController::class, 'show']);
        Route::get('/resources/{id}/download', [ResourceController::class, 'download']);
        Route::put('/resources/{id}', [ResourceController::class, 'update']);
        Route::delete('/resources/{id}', [ResourceController::class, 'destroy']);
        
        // Resource Requests
        Route::get('/resource-requests', [ResourceRequestController::class, 'index']);
        Route::post('/resource-requests', [ResourceRequestController::class, 'store']);
        Route::get('/resource-requests/{id}', [ResourceRequestController::class, 'show']);
        Route::put('/resource-requests/{id}', [ResourceRequestController::class, 'update']);
        Route::delete('/resource-requests/{id}', [ResourceRequestController::class, 'destroy']);
        
        // Profile
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']);
        Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar']);
        Route::put('/profile/password', [ProfileController::class, 'changePassword']);
    });
});
