<?php

namespace Database\Seeders;

use App\Models\Message;
use App\Models\MessageAttachment;
use App\Models\User;
use Illuminate\Database\Seeder;

class MessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = User::where('role', '!=', 'admin')->get();

        if ($users->count() < 2) {
            $this->command->warn('Need at least 2 users to create messages. Please run UserSeeder first!');
            return;
        }

        $subjects = [
            'Question about assignment',
            'Schedule change request',
            'Progress update',
            'Homework clarification',
            'Meeting request',
            'Grade inquiry',
            'Resource request',
        ];

        $bodies = [
            'I have a question regarding the assignment due next week.',
            'Could we reschedule our session to next Tuesday?',
            'I wanted to update you on my progress.',
            'I need clarification on the homework instructions.',
            'Would it be possible to schedule a meeting?',
            'Could you please explain my recent grade?',
            'Could you share the study materials from last class?',
        ];

        // Create message threads
        for ($i = 0; $i < 20; $i++) {
            $sender = $users->random();
            $recipient = $users->where('id', '!=', $sender->id)->random();
            
            $threadId = 'thread-' . uniqid();
            $subject = $subjects[array_rand($subjects)];
            $body = $bodies[array_rand($bodies)];

            // Create initial message
            $message = Message::create([
                'thread_id' => $threadId,
                'sender_id' => $sender->id,
                'recipient_id' => $recipient->id,
                'subject' => $subject,
                'body' => $body,
                'is_read' => rand(0, 10) < 7,
            ]);

            // 30% chance of attachment
            if (rand(0, 10) < 3) {
                MessageAttachment::create([
                    'message_id' => $message->id,
                    'name' => 'document_' . $message->id . '.pdf',
                    'file_path' => 'messages/attachments/file_' . $message->id . '.pdf',
                    'file_size' => rand(100000, 5000000),
                    'mime_type' => 'application/pdf',
                ]);
            }

            // Create reply messages (1-3 replies per thread)
            $replyCount = rand(1, 3);
            for ($j = 0; $j < $replyCount; $j++) {
                $replySender = $j % 2 === 0 ? $recipient : $sender;
                $replyRecipient = $j % 2 === 0 ? $sender : $recipient;

                Message::create([
                    'thread_id' => $threadId,
                    'sender_id' => $replySender->id,
                    'recipient_id' => $replyRecipient->id,
                    'subject' => 'Re: ' . $subject,
                    'body' => 'Thank you for your message. ' . $bodies[array_rand($bodies)],
                    'is_read' => rand(0, 10) < 6,
                ]);
            }
        }

        $this->command->info('Messages seeded successfully!');
        $this->command->info('Total: ' . Message::count() . ' messages created');
        $this->command->info('Total: ' . MessageAttachment::count() . ' attachments created');
    }
}

