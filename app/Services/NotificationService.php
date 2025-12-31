<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Create a notification for a user
     */
    public function createNotification(int $userId, string $type, string $title, string $message, array $data = [], string $priority = 'medium'): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'priority' => $priority,
            'is_read' => false,
        ]);
    }

    /**
     * Notify multiple users
     */
    public function notifyUsers(array $userIds, string $type, string $title, string $message, array $data = [], string $priority = 'medium'): int
    {
        $notifications = [];
        foreach ($userIds as $userId) {
            $notifications[] = [
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => json_encode($data), // JSON encode the data array for bulk insert
                'priority' => $priority,
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Notification::insert($notifications);
        return count($notifications);
    }

    /**
     * Notify user about new grade
     */
    public function notifyGradePosted(int $studentId, string $subject, float $grade): void
    {
        $student = User::find($studentId);
        if (!$student || !$student->student) {
            return;
        }

        $this->createNotification(
            $studentId,
            'grade',
            'New Grade Posted',
            "You received a grade of {$grade} for {$subject}",
            ['subject' => $subject, 'grade' => $grade],
            'medium'
        );

        // Notify parent if exists
        if ($student->student->parent_id) {
            $this->createNotification(
                $student->student->parent_id,
                'grade',
                'Child Grade Posted',
                "{$student->name} received a grade of {$grade} for {$subject}",
                ['student_id' => $studentId, 'subject' => $subject, 'grade' => $grade],
                'medium'
            );
        }
    }

    /**
     * Notify user about new assignment
     */
    public function notifyNewAssignment(int $studentId, string $assignmentTitle): void
    {
        $this->createNotification(
            $studentId,
            'assignment',
            'New Assignment',
            "A new assignment '{$assignmentTitle}' has been posted",
            ['assignment_title' => $assignmentTitle],
            'high'
        );
    }

    /**
     * Notify user about new message
     */
    public function notifyNewMessage(int $recipientId, string $senderName, string $subject): void
    {
        $this->createNotification(
            $recipientId,
            'message',
            'New Message',
            "You received a new message from {$senderName}: {$subject}",
            ['sender_name' => $senderName, 'subject' => $subject],
            'medium'
        );
    }

    /**
     * Notify user about invoice
     */
    public function notifyInvoiceCreated(int $userId, string $invoiceNumber, float $amount): void
    {
        $this->createNotification(
            $userId,
            'payment',
            'New Invoice',
            "A new invoice {$invoiceNumber} has been issued for $" . number_format($amount, 2),
            ['invoice_number' => $invoiceNumber, 'amount' => $amount],
            'high'
        );
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId): bool
    {
        return Notification::where('id', $notificationId)
            ->update(['is_read' => true, 'read_at' => now()]);
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);
    }
}

