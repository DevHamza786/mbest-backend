<?php

namespace Database\Seeders;

use App\Models\Subscription;
use App\Models\Student;
use App\Models\ParentModel;
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

        if ($students->isEmpty()) {
            $this->command->warn('Please run UserSeeder first!');
            return;
        }

        $planTypes = ['monthly', 'quarterly', 'semester', 'annual'];
        $planNames = ['Basic Plan', 'Standard Plan', 'Premium Plan', 'Elite Plan'];
        $billingCycles = ['monthly', 'quarterly', 'yearly'];
        $statuses = ['active', 'expired', 'cancelled', 'pending'];

        foreach ($students as $student) {
            $parentUser = $student->parents()->first(); // This returns a User, not ParentModel
            
            // 70% of students have subscriptions
            if (rand(0, 10) < 7) {
                $planType = $planTypes[array_rand($planTypes)];
                $startDate = Carbon::now()->subMonths(rand(0, 6));
                
                // Calculate end date based on plan type
                $endDate = match($planType) {
                    'monthly' => $startDate->copy()->addMonth(),
                    'quarterly' => $startDate->copy()->addMonths(3),
                    'semester' => $startDate->copy()->addMonths(6),
                    'annual' => $startDate->copy()->addYear(),
                    default => $startDate->copy()->addMonth(),
                };

                $status = $endDate->isPast() ? 'expired' : ($statuses[array_rand($statuses)]);
                $billingCycle = match($planType) {
                    'monthly' => 'monthly',
                    'quarterly' => 'quarterly',
                    'semester', 'annual' => 'yearly',
                    default => 'monthly',
                };
                $price = match($planType) {
                    'monthly' => rand(200, 400),
                    'quarterly' => rand(500, 1000),
                    'semester' => rand(1000, 1800),
                    'annual' => rand(2000, 3500),
                    default => 200,
                };

                Subscription::create([
                    'student_id' => $student->id,
                    'parent_id' => $parentUser ? $parentUser->id : null,
                    'plan_type' => $planType,
                    'plan_name' => $planNames[array_rand($planNames)],
                    'price' => $price,
                    'currency' => 'USD',
                    'billing_cycle' => $billingCycle,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => $status,
                    'auto_renew' => rand(0, 10) < 7, // 70% auto-renew
                ]);
            }
        }

        $this->command->info('Subscriptions seeded successfully!');
        $this->command->info('Total: ' . Subscription::count() . ' subscriptions created');
    }
}

