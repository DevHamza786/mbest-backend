<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Tutor;
use App\Models\Student;
use App\Models\ParentModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@mbest.com'],
            [
                'name' => 'System Admin',
                'password' => Hash::make('Password123!'),
                'role' => 'admin',
                'phone' => '+61412345678',
                'is_active' => true,
            ]
        );

        // 2. Tutor User
        $tutorUser = User::firstOrCreate(
            ['email' => 'tutor@mbest.com'],
            [
                'name' => 'Sarah Connor (Tutor)',
                'password' => Hash::make('Password123!'),
                'role' => 'tutor',
                'phone' => '+61423456789',
                'date_of_birth' => '1990-04-12',
                'address' => 'Sydney, Australia',
                'is_active' => true,
            ]
        );

        $tutor = Tutor::firstOrCreate(
            ['user_id' => $tutorUser->id],
            [
                'department' => 'Mathematics & Science',
                'specialization' => ['HSC Mathematics', 'Physics', 'Chemistry'],
                'hourly_rate' => 75.00,
                'bio' => 'Experienced senior educator with over 8 years of specialized tutoring.',
                'qualifications' => 'B.Ed Mathematics, M.Sc Physics',
                'experience_years' => 8,
                'wwcc_number' => 'WWC1234567E',
                'wwcc_expiry_date' => '2028-12-31',
                'max_students_per_group' => 10,
                'profile_complete' => true,
                'is_available' => true,
            ]
        );

        // 3. Parent User
        $parentUser = User::firstOrCreate(
            ['email' => 'parent@mbest.com'],
            [
                'name' => 'Michael Smith (Parent)',
                'password' => Hash::make('Password123!'),
                'role' => 'parent',
                'phone' => '+61434567890',
                'date_of_birth' => '1982-08-20',
                'address' => 'Melbourne, Australia',
                'is_active' => true,
            ]
        );

        $parent = ParentModel::firstOrCreate(
            ['user_id' => $parentUser->id],
            [
                'relationship' => 'father',
            ]
        );

        // 4. Student User
        $studentUser = User::firstOrCreate(
            ['email' => 'student@mbest.com'],
            [
                'name' => 'Alex Smith (Student)',
                'password' => Hash::make('Password123!'),
                'role' => 'student',
                'phone' => '+61445678901',
                'date_of_birth' => '2008-05-15',
                'address' => 'Melbourne, Australia',
                'is_active' => true,
            ]
        );

        $student = Student::firstOrCreate(
            ['user_id' => $studentUser->id],
            [
                'parent_id' => $parentUser->id,
                'enrollment_id' => 'STU-2026-001',
                'school' => 'Melbourne High School',
                'grade' => 'Year 11',
            ]
        );

        $this->command->info('✅ Seeded demo accounts for testing:');
        $this->command->info('   Admin:   admin@mbest.com / Password123!');
        $this->command->info('   Tutor:   tutor@mbest.com / Password123!');
        $this->command->info('   Parent:  parent@mbest.com / Password123!');
        $this->command->info('   Student: student@mbest.com / Password123!');
    }
}
