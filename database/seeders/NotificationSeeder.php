<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            $this->command->warn('Please run UserSeeder first!');
            return;
        }

        $types = ['assignment', 'grade', 'message', 'session', 'invoice', 'system'];
        $titles = [
            'assignment' => ['New Assignment Posted', 'Assignment Due Soon', 'Assignment Graded'],
            'grade' => ['New Grade Available', 'Grade Updated'],
            'message' => ['New Message Received', 'Message Reply'],
            'session' => ['Session Scheduled', 'Session Reminder', 'Session Cancelled'],
            'invoice' => ['New Invoice', 'Invoice Due Soon', 'Invoice Paid'],
            'system' => ['System Update', 'Welcome to MBEST', 'Profile Updated'],
        ];

        $messages = [
            'assignment' => [
                'A new assignment has been posted for your class.',
                'You have an assignment due in 2 days.',
                'Your assignment has been graded.',
            ],
            'grade' => [
                'A new grade is available for review.',
                'Your grade has been updated.',
            ],
            'message' => [
                'You have received a new message.',
                'Someone replied to your message.',
            ],
            'session' => [
                'A new tutoring session has been scheduled.',
                'Reminder: You have a session tomorrow.',
                'Your session has been cancelled.',
            ],
            'invoice' => [
                'A new invoice has been generated.',
                'Your invoice is due in 3 days.',
                'Your invoice payment has been received.',
            ],
            'system' => [
                'System maintenance scheduled for tonight.',
                'Welcome to MBEST Learning Management System!',
                'Your profile has been successfully updated.',
            ],
        ];

        foreach ($users as $user) {
            // Create 5-15 notifications per user
            $notificationCount = rand(5, 15);
            
            for ($i = 0; $i < $notificationCount; $i++) {
                $type = $types[array_rand($types)];
                $titleIndex = array_rand($titles[$type]);
                $messageIndex = array_rand($messages[$type]);

                Notification::create([
                    'user_id' => $user->id,
                    'type' => $type,
                    'title' => $titles[$type][$titleIndex],
                    'message' => $messages[$type][$messageIndex],
                    'data' => json_encode(['related_id' => rand(1, 100)]),
                    'is_read' => rand(0, 10) < 4, // 40% read
                ]);
            }
        }

        $this->command->info('Notifications seeded successfully!');
        $this->command->info('Total: ' . Notification::count() . ' notifications created');
    }
}

