<?php

namespace Database\Seeders;

use App\Models\TutoringSession;
use App\Models\Tutor;
use App\Models\Student;
use App\Models\StudentNote;
use App\Models\ClassModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SessionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tutors = Tutor::all();
        $students = Student::all();
        $classes = ClassModel::all();

        if ($tutors->isEmpty() || $students->isEmpty()) {
            $this->command->warn('Please run UserSeeder and ClassSeeder first!');
            return;
        }

        $subjects = ['Mathematics', 'Physics', 'Chemistry', 'English', 'Computer Science'];
        $yearLevels = ['Year 9', 'Year 10', 'Year 11', 'Year 12'];
        $locations = ['online', 'centre', 'home']; // Must match enum values
        $sessionTypes = ['1:1', 'group']; // Must match enum values
        $statuses = ['planned', 'completed', 'cancelled', 'no-show', 'rescheduled']; // Must match enum values
        $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'];

        // Create sessions for the past 30 days and next 30 days
        for ($i = -30; $i <= 30; $i++) {
            $date = Carbon::now()->addDays($i);
            
            // Skip weekends
            if ($date->isWeekend()) {
                continue;
            }

            // Create 2-5 sessions per day
            $sessionsPerDay = rand(2, 5);
            
            for ($j = 0; $j < $sessionsPerDay; $j++) {
                $tutor = $tutors->random();
                $subject = $subjects[array_rand($subjects)];
                $yearLevel = $yearLevels[array_rand($yearLevels)];
                
                // Determine status based on date
                if ($i < 0) {
                    $status = rand(0, 10) < 8 ? 'completed' : (rand(0, 10) < 8 ? 'cancelled' : 'no-show');
                } else {
                    $status = 'planned';
                }

                $startTime = Carbon::parse($date->format('Y-m-d') . ' ' . ['09:00', '10:30', '13:00', '14:30', '16:00'][rand(0, 4)]);
                $endTime = $startTime->copy()->addHours(rand(1, 2))->addMinutes(rand(0, 1) * 30);

                $session = TutoringSession::create([
                    'date' => $date,
                    'start_time' => $startTime->format('H:i'),
                    'end_time' => $endTime->format('H:i'),
                    'teacher_id' => $tutor->id,
                    'subject' => $subject,
                    'year_level' => $yearLevel,
                    'location' => $locations[array_rand($locations)],
                    'session_type' => $sessionTypes[array_rand($sessionTypes)],
                    'status' => $status,
                    'lesson_note' => $status === 'completed' ? 'Covered ' . $subject . ' topics. Students showed good understanding.' : null,
                    'topics_taught' => $status === 'completed' ? ['Topic 1', 'Topic 2', 'Topic 3'][rand(0, 2)] : null,
                    'homework_resources' => $status === 'completed' ? 'Textbook pages 45-50, Practice exercises 1-5' : null,
                    'attendance_marked' => $status === 'completed' ? rand(0, 10) < 8 : false,
                    'ready_for_invoicing' => $status === 'completed' ? rand(0, 10) < 7 : false,
                    'color' => $colors[array_rand($colors)],
                ]);

                // Assign students to session
                $studentCount = $session->session_type === 'group' ? rand(2, 5) : 1;
                $selectedStudents = $students->random(min($studentCount, $students->count()));

                foreach ($selectedStudents as $student) {
                    $attendanceStatus = null;
                    if ($status === 'completed' && $session->attendance_marked) {
                        $attendanceStatus = ['present', 'present', 'present', 'late', 'absent', 'excused'][rand(0, 5)];
                    }

                    DB::table('session_student')->insert([
                        'session_id' => $session->id,
                        'student_id' => $student->id,
                        'attendance_status' => $attendanceStatus,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Create student notes for completed sessions
                    if ($status === 'completed' && rand(0, 10) < 6) {
                        StudentNote::create([
                            'session_id' => $session->id,
                            'student_id' => $student->id,
                            'homework_completed' => rand(0, 10) < 7,
                            'private_notes' => ['Excellent progress', 'Needs more practice', 'Good participation', 'Shows improvement'][rand(0, 3)],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }

        $this->command->info('Sessions seeded successfully!');
        $this->command->info('Total: ' . TutoringSession::count() . ' sessions created');
    }
}

