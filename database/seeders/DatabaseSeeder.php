<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting complete database seeding for MBEST LMS...');
        $this->command->newLine();

        $this->call([
            UserSeeder::class,
            ClassSeeder::class,
            FiveClassesForTutorSeeder::class,
            SessionSeeder::class,
            AssignmentSeeder::class,
            GradeSeeder::class,
            ResourceSeeder::class,
            InvoiceSeeder::class,
            LessonRequestSeeder::class,
            MessageSeeder::class,
            NotificationSeeder::class,
            TutorAvailabilitySeeder::class,
            PackageSeeder::class,
            SubscriptionSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('🎉 All seeders executed successfully!');
        $this->command->newLine();
        $this->command->info('🔑 Demo Login Credentials (Password for all: Password123!)');
        $this->command->info('   👑 Admin:   admin@mbest.com');
        $this->command->info('   👨‍🏫 Tutor:   tutor@mbest.com');
        $this->command->info('   👨‍👩‍👧 Parent:  parent@mbest.com');
        $this->command->info('   🎓 Student: student@mbest.com');
    }
}
