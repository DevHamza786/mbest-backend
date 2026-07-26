<?php

namespace Database\Seeders;

use App\Models\Package;
use App\Models\ClassModel;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packagesData = [
            [
                'name' => 'HSC Mathematics Extension 1 Package',
                'price' => 299.00,
                'description' => 'Comprehensive 12-week HSC Mathematics Extension 1 program including weekly live classes, personalized assignments, and past paper revision.',
                'subject' => 'HSC Mathematics',
                'billing_type' => 'monthly',
                'package_type' => 'group',
                'student_limit' => 10,
                'allows_one_on_one' => false,
                'bank_details' => 'BSB: 123-456 Acc: 98765432 (MBEST Education)',
                'is_active' => true,
            ],
            [
                'name' => '1-on-1 Premium Tutoring (Physics & Chemistry)',
                'price' => 450.00,
                'description' => 'Direct 1-on-1 specialized tutoring sessions with senior educators, personalized homework support, and exam strategies.',
                'subject' => 'Physics & Chemistry',
                'billing_type' => 'monthly',
                'package_type' => '1on1',
                'student_limit' => 1,
                'allows_one_on_one' => true,
                'bank_details' => 'BSB: 123-456 Acc: 98765432 (MBEST Education)',
                'is_active' => true,
            ],
            [
                'name' => 'Year 7-10 Junior Science & Mathematics Bundle',
                'price' => 199.00,
                'description' => 'Foundational STEM course for Year 7 to Year 10 students covering essential concepts in Mathematics, Physics, and Chemistry.',
                'subject' => 'Junior Science',
                'billing_type' => 'monthly',
                'package_type' => 'group',
                'student_limit' => 15,
                'allows_one_on_one' => false,
                'bank_details' => 'BSB: 123-456 Acc: 98765432 (MBEST Education)',
                'is_active' => true,
            ],
            [
                'name' => 'Elite All-Access Annual Pass',
                'price' => 2499.00,
                'description' => 'Full annual subscription granting unlimited access to all group classes, 1-on-1 consultation sessions, resource library, and priority support.',
                'subject' => 'All Subjects',
                'billing_type' => 'termly',
                'package_type' => 'both',
                'student_limit' => 20,
                'allows_one_on_one' => true,
                'bank_details' => 'BSB: 123-456 Acc: 98765432 (MBEST Education)',
                'is_active' => true,
            ],
        ];

        $classes = ClassModel::all();

        foreach ($packagesData as $data) {
            $package = Package::updateOrCreate(
                ['name' => $data['name']],
                $data
            );

            if ($classes->isNotEmpty() && ($data['package_type'] === 'group' || $data['package_type'] === 'both')) {
                $package->classes()->sync($classes->pluck('id')->take(2));
            }
        }

        $this->command->info('✅ Subscription packages seeded successfully!');
        $this->command->info('   Total: ' . Package::count() . ' packages created');
    }
}
