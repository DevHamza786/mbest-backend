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

        // Production/live testing: only seed a single admin user.
        // (Other seeders are intentionally NOT executed to avoid dummy data.)
        $this->call([
            AdminSeeder::class,
        ]);

        $this->command->newLine();
        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->newLine();
        $this->command->info('Default login credentials:');
        $this->command->info('  Admin: admin@mbest.com / password123');
    }
}
