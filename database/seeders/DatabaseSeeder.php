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
        $this->command->info('Starting database seeding...');
        $this->command->newLine();

        // Seed in order to maintain relationships
        $this->call([
            UserSeeder::class,              // Users, Tutors, Students, Parents
            ClassSeeder::class,             // Classes and Schedules (depends on Users)
            TutorAvailabilitySeeder::class, // Tutor Availability (depends on Tutors)
            SessionSeeder::class,           // Tutoring Sessions (depends on Users, Classes)
            AssignmentSeeder::class,        // Assignments and Submissions (depends on Classes)
            GradeSeeder::class,             // Grades (depends on Assignments)
            InvoiceSeeder::class,          // Invoices (depends on Users)
            MessageSeeder::class,          // Messages (depends on Users)
            LessonRequestSeeder::class,     // Lesson Requests (depends on Users, Tutors, Students)
            NotificationSeeder::class,      // Notifications (depends on Users)
            ResourceSeeder::class,          // Resources (depends on Classes)
            SubscriptionSeeder::class,      // Subscriptions (depends on Students)
        ]);

        $this->command->newLine();
        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->newLine();
        $this->command->info('Default login credentials:');
        $this->command->info('  Admin: admin@mbest.com / password123');
        $this->command->info('  Tutor: michael.rodriguez@mbest.com / password123');
        $this->command->info('  Student: emma.thompson@mbest.com / password123');
        $this->command->info('  Parent: robert.thompson@mbest.com / password123');
    }
}
