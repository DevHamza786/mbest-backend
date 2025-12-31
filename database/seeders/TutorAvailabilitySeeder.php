<?php

namespace Database\Seeders;

use App\Models\TutorAvailability;
use App\Models\Tutor;
use Illuminate\Database\Seeder;

class TutorAvailabilitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tutors = Tutor::all();

        if ($tutors->isEmpty()) {
            $this->command->warn('Please run UserSeeder first!');
            return;
        }

        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $timeSlots = [
            ['09:00', '12:00'],
            ['13:00', '17:00'],
            ['18:00', '21:00'],
        ];

        foreach ($tutors as $tutor) {
            // Each tutor has availability for 3-5 days
            $availableDays = array_rand($daysOfWeek, rand(3, 5));
            if (!is_array($availableDays)) {
                $availableDays = [$availableDays];
            }

            foreach ($availableDays as $dayIndex) {
                $timeSlot = $timeSlots[array_rand($timeSlots)];

                TutorAvailability::create([
                    'tutor_id' => $tutor->id,
                    'day_of_week' => $daysOfWeek[$dayIndex],
                    'start_time' => $timeSlot[0],
                    'end_time' => $timeSlot[1],
                    'is_available' => true,
                ]);
            }
        }

        $this->command->info('Tutor availability seeded successfully!');
        $this->command->info('Total: ' . TutorAvailability::count() . ' availability records created');
    }
}

