<?php

namespace Database\Seeders;

use App\Models\ClassModel;
use App\Models\ClassSchedule;
use App\Models\Tutor;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tutors = Tutor::all();
        $students = Student::all();

        if ($tutors->isEmpty() || $students->isEmpty()) {
            $this->command->warn('Please run UserSeeder first!');
            return;
        }

        $classes = [
            [
                'name' => 'Advanced Mathematics',
                'code' => 'MATH301',
                'description' => 'Advanced mathematics course covering algebra, calculus, and statistics',
                'category' => 'Mathematics',
                'level' => 'Advanced',
                'capacity' => 30,
                'credits' => 4,
                'duration' => '16 weeks',
                'status' => 'active',
            ],
            [
                'name' => 'Web Development Fundamentals',
                'code' => 'CS101',
                'description' => 'Introduction to web development using HTML, CSS, and JavaScript',
                'category' => 'Computer Science',
                'level' => 'Beginner',
                'capacity' => 25,
                'credits' => 3,
                'duration' => '12 weeks',
                'status' => 'active',
            ],
            [
                'name' => 'Physics Mechanics',
                'code' => 'PHYS201',
                'description' => 'Comprehensive study of mechanics, forces, and motion',
                'category' => 'Physics',
                'level' => 'Intermediate',
                'capacity' => 28,
                'credits' => 4,
                'duration' => '14 weeks',
                'status' => 'active',
            ],
            [
                'name' => 'English Literature',
                'code' => 'ENG201',
                'description' => 'Study of classic and modern English literature',
                'category' => 'English',
                'level' => 'Intermediate',
                'capacity' => 20,
                'credits' => 3,
                'duration' => '12 weeks',
                'status' => 'active',
            ],
            [
                'name' => 'Organic Chemistry',
                'code' => 'CHEM301',
                'description' => 'Advanced organic chemistry concepts and reactions',
                'category' => 'Chemistry',
                'level' => 'Advanced',
                'capacity' => 22,
                'credits' => 4,
                'duration' => '16 weeks',
                'status' => 'active',
            ],
            [
                'name' => 'Data Structures and Algorithms',
                'code' => 'CS301',
                'description' => 'Advanced programming concepts and algorithm design',
                'category' => 'Computer Science',
                'level' => 'Advanced',
                'capacity' => 24,
                'credits' => 4,
                'duration' => '14 weeks',
                'status' => 'active',
            ],
        ];

        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
        $timeSlots = [
            ['09:00', '10:30'],
            ['10:30', '12:00'],
            ['13:00', '14:30'],
            ['14:30', '16:00'],
            ['16:00', '17:30'],
        ];

        foreach ($classes as $classData) {
            $tutor = $tutors->random();
            
            $class = ClassModel::create([
                'name' => $classData['name'],
                'code' => $classData['code'],
                'tutor_id' => $tutor->id,
                'description' => $classData['description'],
                'category' => $classData['category'],
                'level' => $classData['level'],
                'capacity' => $classData['capacity'],
                'enrolled' => 0,
                'credits' => $classData['credits'],
                'duration' => $classData['duration'],
                'status' => $classData['status'],
                'start_date' => now()->addDays(rand(0, 30)),
                'end_date' => now()->addDays(rand(90, 120)),
            ]);

            // Create class schedules
            $scheduleDays = array_rand($daysOfWeek, rand(2, 3));
            if (!is_array($scheduleDays)) {
                $scheduleDays = [$scheduleDays];
            }

            foreach ($scheduleDays as $dayIndex) {
                $timeSlot = $timeSlots[array_rand($timeSlots)];
                $isOnline = rand(0, 10) < 4; // 40% chance of online
                
                ClassSchedule::create([
                    'class_id' => $class->id,
                    'day_of_week' => $daysOfWeek[$dayIndex],
                    'start_time' => $timeSlot[0],
                    'end_time' => $timeSlot[1],
                    'room' => $isOnline ? null : ['Room 101', 'Room 202', 'Room 303', 'Lab A'][rand(0, 3)],
                    'meeting_link' => $isOnline ? 'https://meet.example.com/class-' . $class->id : null,
                ]);
            }

            // Enroll random students
            $enrollmentCount = rand(5, min(15, $class->capacity));
            $selectedStudents = $students->random(min($enrollmentCount, $students->count()));

            foreach ($selectedStudents as $student) {
                DB::table('class_student')->insert([
                    'class_id' => $class->id,
                    'student_id' => $student->id,
                    'enrolled_at' => now()->subDays(rand(1, 30)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $class->update(['enrolled' => $selectedStudents->count()]);
        }

        $this->command->info('Classes seeded successfully!');
        $this->command->info('Total: ' . ClassModel::count() . ' classes created');
    }
}

