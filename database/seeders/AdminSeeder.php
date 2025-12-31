<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Tutor;
use App\Models\Student;
use App\Models\ParentModel;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin User
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@mbest.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Create Sample Tutor
        $tutorUser = User::create([
            'name' => 'Dr. Michael Rodriguez',
            'email' => 'tutor@mbest.com',
            'password' => Hash::make('password123'),
            'role' => 'tutor',
            'is_active' => true,
        ]);

        Tutor::create([
            'user_id' => $tutorUser->id,
            'department' => 'Computer Science',
            'specialization' => ['Web Development', 'Data Structures'],
            'hourly_rate' => 50.00,
            'bio' => 'Experienced tutor in computer science with 10+ years of teaching experience.',
            'qualifications' => 'PhD in Computer Science, M.Sc. in Software Engineering',
            'experience_years' => 10,
            'is_available' => true,
        ]);

        // Create Sample Student
        $studentUser = User::create([
            'name' => 'Emma Thompson',
            'email' => 'student@mbest.com',
            'password' => Hash::make('password123'),
            'role' => 'student',
            'is_active' => true,
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'enrollment_id' => 'ENR-2024-001',
            'grade' => 'Year 10',
            'school' => 'Local High School',
            'emergency_contact_name' => 'Robert Thompson',
            'emergency_contact_phone' => '+1234567890',
        ]);

        // Create Sample Parent
        $parentUser = User::create([
            'name' => 'Robert Thompson',
            'email' => 'parent@mbest.com',
            'password' => Hash::make('password123'),
            'role' => 'parent',
            'is_active' => true,
        ]);

        ParentModel::create([
            'user_id' => $parentUser->id,
            'relationship' => 'Father',
        ]);

        // Link parent to student via pivot table
        $student = Student::where('user_id', $studentUser->id)->first();
        if ($student) {
            DB::table('parent_student')->insert([
                'parent_id' => $parentUser->id,
                'student_id' => $student->id,
                'relationship' => 'parent',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Admin, Tutor, Student, and Parent users created successfully!');
        $this->command->info('Admin: admin@mbest.com / password123');
        $this->command->info('Tutor: tutor@mbest.com / password123');
        $this->command->info('Student: student@mbest.com / password123');
        $this->command->info('Parent: parent@mbest.com / password123');
    }
}

