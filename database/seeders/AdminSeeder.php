<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed ONLY admin for live testing (no dummy tutor/student/parent).
        $adminEmail = 'admin@mbest.com';

        // Ensure idempotency: update existing admin by email.
        $user = User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password123'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        $this->command->info('✅ Admin user seeded successfully!');
        $this->command->info('Admin: ' . $adminEmail . ' / password123');
    }
}

