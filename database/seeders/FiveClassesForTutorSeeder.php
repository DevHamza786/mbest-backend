<?php

namespace Database\Seeders;

use App\Models\ClassModel;
use App\Models\Tutor;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeds exactly 5 classes assigned to one tutor.
 *
 * Resolution order:
 * 1. SEED_CLASSES_TUTOR_ID — tutors.id
 * 2. SEED_TUTOR_USER_ID — users.id (default 2)
 * 3. First tutor in the database
 *
 * Run: php artisan db:seed --class=FiveClassesForTutorSeeder
 */
class FiveClassesForTutorSeeder extends Seeder
{
    public function run(): void
    {
        $tutorId = (int) env('SEED_CLASSES_TUTOR_ID', 0);
        $tutor = $tutorId > 0 ? Tutor::find($tutorId) : null;

        if (! $tutor) {
            $userId = (int) env('SEED_TUTOR_USER_ID', 2);
            $tutor = Tutor::where('user_id', $userId)->first();
        }

        if (! $tutor) {
            $tutor = Tutor::query()->orderBy('id')->first();
        }

        if (! $tutor) {
            $this->command->error('No tutor found. Create tutor users first.');

            return;
        }

        $this->command->info("Assigning 5 classes to tutor id {$tutor->id} (user_id {$tutor->user_id}).");

        $start = Carbon::today()->addWeek();

        $rows = [
            [
                'name' => 'Algebra Foundations',
                'code' => 'SEED-MB-01',
                'description' => 'Core algebra skills for secondary students.',
                'category' => 'Mathematics',
                'level' => 'Beginner',
                'capacity' => 24,
                'credits' => 3,
                'duration' => '2',
                'status' => 'active',
            ],
            [
                'name' => 'Calculus Intro',
                'code' => 'SEED-MB-02',
                'description' => 'Limits, derivatives, and introductory integration.',
                'category' => 'Mathematics',
                'level' => 'Intermediate',
                'capacity' => 20,
                'credits' => 4,
                'duration' => '3',
                'status' => 'active',
            ],
            [
                'name' => 'Physics Lab Concepts',
                'code' => 'SEED-MB-03',
                'description' => 'Hands-on physics concepts and problem solving.',
                'category' => 'Physics',
                'level' => 'Intermediate',
                'capacity' => 18,
                'credits' => 3,
                'duration' => '2',
                'status' => 'active',
            ],
            [
                'name' => 'Programming Basics',
                'code' => 'SEED-MB-04',
                'description' => 'Variables, control flow, and simple projects.',
                'category' => 'Computer Science',
                'level' => 'Beginner',
                'capacity' => 22,
                'credits' => 3,
                'duration' => '2',
                'status' => 'active',
            ],
            [
                'name' => 'Chemistry Essentials',
                'code' => 'SEED-MB-05',
                'description' => 'Atomic structure, bonding, and reactions.',
                'category' => 'Chemistry',
                'level' => 'Advanced',
                'capacity' => 16,
                'credits' => 4,
                'duration' => '3',
                'status' => 'active',
            ],
        ];

        foreach ($rows as $i => $data) {
            $classStart = $start->copy()->addWeeks($i);
            $classEnd = $classStart->copy()->addWeeks(12);

            ClassModel::updateOrCreate(
                ['code' => $data['code']],
                array_merge($data, [
                    'tutor_id' => $tutor->id,
                    'enrolled' => 0,
                    'start_date' => $classStart->toDateString(),
                    'end_date' => $classEnd->toDateString(),
                ])
            );
        }

        $this->command->info('FiveClassesForTutorSeeder completed: 5 classes for tutor id '.$tutor->id.'.');
    }
}
