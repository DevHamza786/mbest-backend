<?php

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class SubscriptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $students = Student::all();
        $parentUser = User::where('role', 'parent')->first() ?? User::first();

        if ($students->isEmpty()) {
            $this->command->warn('Please run UserSeeder first!');
            return;
        }

        $plans = [
            [
                'name' => 'HSC Mathematics Extension 1 Package',
                'type' => 'monthly',
                'cycle' => 'monthly',
                'price' => 299.00,
            ],
            [
                'name' => '1-on-1 Premium Tutoring (Physics & Chemistry)',
                'type' => 'semester',
                'cycle' => 'quarterly',
                'price' => 450.00,
            ],
            [
                'name' => 'Elite All-Access Annual Pass',
                'type' => 'annual',
                'cycle' => 'yearly',
                'price' => 2499.00,
            ],
        ];

        foreach ($students as $index => $student) {
            $plan = $plans[$index % count($plans)];
            $startDate = Carbon::now()->subMonths(1);
            $endDate = Carbon::now()->addMonths(5);

            Subscription::updateOrCreate(
                ['student_id' => $student->id],
                [
                    'parent_id' => $student->parent_id ?? ($parentUser ? $parentUser->id : null),
                    'plan_type' => $plan['type'],
                    'plan_name' => $plan['name'],
                    'price' => $plan['price'],
                    'currency' => 'AUD',
                    'billing_cycle' => $plan['cycle'],
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'active',
                    'auto_renew' => true,
                ]
            );
        }

        $this->command->info('✅ Student subscriptions seeded successfully!');
        $this->command->info('   Total: ' . Subscription::count() . ' subscriptions created');
    }
}
