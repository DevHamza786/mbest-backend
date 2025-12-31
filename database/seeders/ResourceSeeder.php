<?php

namespace Database\Seeders;

use App\Models\Resource;
use App\Models\ClassModel;
use App\Models\User;
use Illuminate\Database\Seeder;

class ResourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classes = ClassModel::all();
        $tutors = User::where('role', 'tutor')->get();

        if ($classes->isEmpty() || $tutors->isEmpty()) {
            $this->command->warn('Please run UserSeeder and ClassSeeder first!');
            return;
        }

        $resourceTitles = [
            'Study Guide Chapter 1',
            'Practice Problems Set',
            'Lecture Notes Week 1',
            'Reference Material',
            'Video Tutorial',
            'Sample Exam Questions',
            'Formula Sheet',
            'Reading Assignment',
            'Lab Manual',
            'Supplementary Material',
        ];

        $types = ['document', 'pdf', 'video', 'link'];
        $categories = ['Study Material', 'Assignment', 'Reference', 'Video', 'Document'];

        foreach ($classes as $class) {
            // Create 3-6 resources per class
            $resourceCount = rand(3, 6);
            
            for ($i = 0; $i < $resourceCount; $i++) {
                $tutor = $tutors->random();
                $type = $types[array_rand($types)];
                $title = $resourceTitles[array_rand($resourceTitles)] . ' - ' . $class->name;

                Resource::create([
                    'uploaded_by' => $tutor->id, // Changed from user_id
                    'class_id' => $class->id,
                    'title' => $title,
                    'description' => 'This resource contains important study materials for ' . $class->name,
                    'type' => $type,
                    'category' => $categories[array_rand($categories)],
                    'tags' => json_encode([$class->category, $type, $categories[array_rand($categories)]]), // Added tags
                    'url' => $type === 'link' ? 'https://example.com/resource/' . rand(1, 1000) : 'resources/' . $title . '.' . ($type === 'pdf' ? 'pdf' : 'doc'),
                    'file_path' => $type !== 'link' ? 'resources/' . str_replace(' ', '_', strtolower($title)) . '.' . ($type === 'pdf' ? 'pdf' : 'doc') : null,
                    'file_size' => $type !== 'link' ? rand(100000, 10000000) : null,
                    'is_public' => rand(0, 10) < 7, // 70% public
                    'downloads' => rand(0, 100),
                ]);
            }
        }

        $this->command->info('Resources seeded successfully!');
        $this->command->info('Total: ' . Resource::count() . ' resources created');
    }
}

