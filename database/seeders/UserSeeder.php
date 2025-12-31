<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tutor;
use App\Models\Student;
use App\Models\ParentModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin Users
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@mbest.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'phone' => '+1234567890',
            'is_active' => true,
        ]);

        // Create Multiple Tutors
        $tutors = [
            [
                'name' => 'Dr. Michael Rodriguez',
                'email' => 'michael.rodriguez@mbest.com',
                'phone' => '+1234567891',
                'department' => 'Mathematics',
                'specialization' => ['Algebra', 'Calculus', 'Statistics'],
                'hourly_rate' => 55.00,
                'bio' => 'PhD in Mathematics with 15 years of teaching experience. Specialized in advanced calculus and statistics.',
                'qualifications' => 'PhD Mathematics, M.Sc. Applied Mathematics',
                'experience_years' => 15,
            ],
            [
                'name' => 'Prof. Sarah Johnson',
                'email' => 'sarah.johnson@mbest.com',
                'phone' => '+1234567892',
                'department' => 'Computer Science',
                'specialization' => ['Web Development', 'Data Structures', 'Algorithms'],
                'hourly_rate' => 60.00,
                'bio' => 'Senior Software Engineer turned educator. Expert in modern web technologies and programming.',
                'qualifications' => 'M.Sc. Computer Science, B.Eng. Software Engineering',
                'experience_years' => 12,
            ],
            [
                'name' => 'Dr. James Wilson',
                'email' => 'james.wilson@mbest.com',
                'phone' => '+1234567893',
                'department' => 'Physics',
                'specialization' => ['Mechanics', 'Thermodynamics', 'Electromagnetism'],
                'hourly_rate' => 50.00,
                'bio' => 'Physics professor with extensive research background. Makes complex concepts easy to understand.',
                'qualifications' => 'PhD Physics, M.Sc. Applied Physics',
                'experience_years' => 18,
            ],
            [
                'name' => 'Ms. Emily Chen',
                'email' => 'emily.chen@mbest.com',
                'phone' => '+1234567894',
                'department' => 'English',
                'specialization' => ['Literature', 'Writing', 'Grammar'],
                'hourly_rate' => 45.00,
                'bio' => 'Published author and English literature expert. Helps students improve their writing skills.',
                'qualifications' => 'M.A. English Literature, B.A. Creative Writing',
                'experience_years' => 10,
            ],
            [
                'name' => 'Dr. Robert Brown',
                'email' => 'robert.brown@mbest.com',
                'phone' => '+1234567895',
                'department' => 'Chemistry',
                'specialization' => ['Organic Chemistry', 'Biochemistry', 'Analytical Chemistry'],
                'hourly_rate' => 52.00,
                'bio' => 'Chemistry expert with industry experience. Makes chemistry fun and engaging.',
                'qualifications' => 'PhD Chemistry, M.Sc. Organic Chemistry',
                'experience_years' => 14,
            ],
        ];

        foreach ($tutors as $tutorData) {
            $user = User::create([
                'name' => $tutorData['name'],
                'email' => $tutorData['email'],
                'password' => Hash::make('password123'),
                'role' => 'tutor',
                'phone' => $tutorData['phone'],
                'is_active' => true,
            ]);

            Tutor::create([
                'user_id' => $user->id,
                'department' => $tutorData['department'],
                'specialization' => $tutorData['specialization'],
                'hourly_rate' => $tutorData['hourly_rate'],
                'bio' => $tutorData['bio'],
                'qualifications' => $tutorData['qualifications'],
                'experience_years' => $tutorData['experience_years'],
                'is_available' => true,
            ]);
        }

        // Create Multiple Students
        $students = [
            ['name' => 'Emma Thompson', 'email' => 'emma.thompson@mbest.com', 'grade' => 'Year 10', 'enrollment_id' => 'ENR-2024-001'],
            ['name' => 'Lucas Martinez', 'email' => 'lucas.martinez@mbest.com', 'grade' => 'Year 11', 'enrollment_id' => 'ENR-2024-002'],
            ['name' => 'Sophia Anderson', 'email' => 'sophia.anderson@mbest.com', 'grade' => 'Year 9', 'enrollment_id' => 'ENR-2024-003'],
            ['name' => 'Noah Taylor', 'email' => 'noah.taylor@mbest.com', 'grade' => 'Year 10', 'enrollment_id' => 'ENR-2024-004'],
            ['name' => 'Olivia White', 'email' => 'olivia.white@mbest.com', 'grade' => 'Year 12', 'enrollment_id' => 'ENR-2024-005'],
            ['name' => 'William Harris', 'email' => 'william.harris@mbest.com', 'grade' => 'Year 11', 'enrollment_id' => 'ENR-2024-006'],
            ['name' => 'Ava Clark', 'email' => 'ava.clark@mbest.com', 'grade' => 'Year 9', 'enrollment_id' => 'ENR-2024-007'],
            ['name' => 'James Lewis', 'email' => 'james.lewis@mbest.com', 'grade' => 'Year 10', 'enrollment_id' => 'ENR-2024-008'],
            ['name' => 'Isabella Walker', 'email' => 'isabella.walker@mbest.com', 'grade' => 'Year 12', 'enrollment_id' => 'ENR-2024-009'],
            ['name' => 'Benjamin Hall', 'email' => 'benjamin.hall@mbest.com', 'grade' => 'Year 11', 'enrollment_id' => 'ENR-2024-010'],
        ];

        foreach ($students as $studentData) {
            $user = User::create([
                'name' => $studentData['name'],
                'email' => $studentData['email'],
                'password' => Hash::make('password123'),
                'role' => 'student',
                'phone' => '+1' . rand(2000000000, 9999999999),
                'date_of_birth' => now()->subYears(rand(14, 18))->subMonths(rand(0, 11)),
                'is_active' => true,
            ]);

            Student::create([
                'user_id' => $user->id,
                'enrollment_id' => $studentData['enrollment_id'],
                'grade' => $studentData['grade'],
                'school' => ['Local High School', 'City Academy', 'International School', 'Community College'][rand(0, 3)],
                'emergency_contact_name' => 'Parent ' . $studentData['name'],
                'emergency_contact_phone' => '+1' . rand(2000000000, 9999999999),
            ]);
        }

        // Create Multiple Parents
        $parents = [
            ['name' => 'Robert Thompson', 'email' => 'robert.thompson@mbest.com', 'relationship' => 'Father'],
            ['name' => 'Maria Martinez', 'email' => 'maria.martinez@mbest.com', 'relationship' => 'Mother'],
            ['name' => 'David Anderson', 'email' => 'david.anderson@mbest.com', 'relationship' => 'Father'],
            ['name' => 'Jennifer Taylor', 'email' => 'jennifer.taylor@mbest.com', 'relationship' => 'Mother'],
            ['name' => 'Christopher White', 'email' => 'christopher.white@mbest.com', 'relationship' => 'Father'],
            ['name' => 'Lisa Harris', 'email' => 'lisa.harris@mbest.com', 'relationship' => 'Mother'],
            ['name' => 'Mark Clark', 'email' => 'mark.clark@mbest.com', 'relationship' => 'Father'],
            ['name' => 'Patricia Lewis', 'email' => 'patricia.lewis@mbest.com', 'relationship' => 'Mother'],
            ['name' => 'Daniel Walker', 'email' => 'daniel.walker@mbest.com', 'relationship' => 'Father'],
            ['name' => 'Nancy Hall', 'email' => 'nancy.hall@mbest.com', 'relationship' => 'Mother'],
        ];

        $studentUsers = User::where('role', 'student')->get();
        foreach ($parents as $index => $parentData) {
            $user = User::create([
                'name' => $parentData['name'],
                'email' => $parentData['email'],
                'password' => Hash::make('password123'),
                'role' => 'parent',
                'phone' => '+1' . rand(2000000000, 9999999999),
                'is_active' => true,
            ]);

            $parent = ParentModel::create([
                'user_id' => $user->id,
                'relationship' => $parentData['relationship'],
            ]);

            // Link parent to student (parent_id references users table, not parents table)
            if ($studentUsers->count() > $index) {
                $student = Student::where('user_id', $studentUsers[$index]->id)->first();
                if ($student) {
                    DB::table('parent_student')->insert([
                        'parent_id' => $user->id, // Use user_id, not parent->id
                        'student_id' => $student->id,
                        'relationship' => strtolower($parentData['relationship']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Users seeded successfully!');
        $this->command->info('Total: ' . User::count() . ' users created');
        $this->command->info('  - Admins: ' . User::where('role', 'admin')->count());
        $this->command->info('  - Tutors: ' . User::where('role', 'tutor')->count());
        $this->command->info('  - Students: ' . User::where('role', 'student')->count());
        $this->command->info('  - Parents: ' . User::where('role', 'parent')->count());
    }
}
