<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Message;
use App\Models\User;
use App\Models\Tutor;
use App\Models\Student;
use Carbon\Carbon;

class LessonRequestSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating lesson requests...');

        // Get tutors
        $tutors = Tutor::with('user')->get();
        if ($tutors->isEmpty()) {
            $this->command->warn('No tutors found. Please run UserSeeder first.');
            return;
        }

        // Get parents (users with role 'parent')
        $parents = User::where('role', 'parent')->get();
        if ($parents->isEmpty()) {
            $this->command->warn('No parents found. Please run UserSeeder first.');
            return;
        }

        // Get students
        $students = Student::with('user')->get();
        if ($students->isEmpty()) {
            $this->command->warn('No students found. Please run UserSeeder first.');
            return;
        }

        // Sample lesson request data
        $lessonRequests = [
            [
                'student_name' => 'Emma Wilson',
                'lesson_type' => 'Mathematics - Year 10',
                'preferred_date' => Carbon::now()->addDays(5)->format('Y-m-d'),
                'preferred_time' => '16:00',
                'duration' => '1.5 hours',
                'message' => 'Emma needs help with quadratic equations and graphing. She has an upcoming test next week.',
                'status' => 'pending',
            ],
            [
                'student_name' => 'James Chen',
                'lesson_type' => 'Physics - Year 11',
                'preferred_date' => Carbon::now()->addDays(7)->format('Y-m-d'),
                'preferred_time' => '14:00',
                'duration' => '2 hours',
                'message' => 'Needs assistance with kinematics problems for upcoming test. Prefers afternoon sessions.',
                'status' => 'pending',
            ],
            [
                'student_name' => 'Sophie Anderson',
                'lesson_type' => 'Chemistry - Year 12',
                'preferred_date' => Carbon::now()->addDays(3)->format('Y-m-d'),
                'preferred_time' => '10:00',
                'duration' => '1 hour',
                'message' => 'Reviewing organic chemistry concepts. Available weekday mornings.',
                'status' => 'pending',
            ],
            [
                'student_name' => 'Michael Brown',
                'lesson_type' => 'Mathematics - Year 9',
                'preferred_date' => Carbon::now()->addDays(10)->format('Y-m-d'),
                'preferred_time' => '15:30',
                'duration' => '1.5 hours',
                'message' => 'Struggling with algebra basics. Needs regular tutoring sessions.',
                'status' => 'pending',
            ],
            [
                'student_name' => 'Olivia Davis',
                'lesson_type' => 'Biology - Year 11',
                'preferred_date' => Carbon::now()->addDays(6)->format('Y-m-d'),
                'preferred_time' => '11:00',
                'duration' => '1 hour',
                'message' => 'Help needed with cell biology and genetics. Preparing for midterm exam.',
                'status' => 'pending',
            ],
        ];

        $createdCount = 0;

        foreach ($lessonRequests as $index => $requestData) {
            // Select a random tutor
            $tutor = $tutors->random();
            
            // Select a random parent
            $parent = $parents->random();
            
            // Try to find a matching student by name, or use a random student
            $student = $students->firstWhere('user.name', 'like', '%' . explode(' ', $requestData['student_name'])[0] . '%');
            if (!$student) {
                $student = $students->random();
            }

            // Create lesson request data in JSON format for message body
            $lessonRequestData = [
                'student_id' => $student->id,
                'student_name' => $requestData['student_name'],
                'lesson_type' => $requestData['lesson_type'],
                'preferred_date' => $requestData['preferred_date'],
                'preferred_time' => $requestData['preferred_time'],
                'duration' => $requestData['duration'],
                'duration_hours' => floatval(str_replace(' hours', '', str_replace(' hour', '', $requestData['duration']))),
                'message' => $requestData['message'],
                'status' => $requestData['status'],
            ];

            // Create message with lesson request subject
            $threadId = 'lesson-request-' . uniqid();
            
            $message = Message::create([
                'thread_id' => $threadId,
                'sender_id' => $parent->id, // Parent sends the request
                'recipient_id' => $tutor->user->id, // Tutor receives it
                'subject' => 'Lesson Request: ' . $requestData['lesson_type'],
                'body' => json_encode($lessonRequestData),
                'is_read' => false,
                'is_important' => false,
            ]);

            $createdCount++;
            
            $this->command->info("  ✓ Created lesson request from {$parent->name} to {$tutor->user->name} for {$requestData['student_name']}");
        }

        $this->command->info("✅ Created {$createdCount} lesson requests");
        $this->command->newLine();
    }
}

