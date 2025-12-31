<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Student;
use App\Models\ClassModel;
use Illuminate\Database\Seeder;

class GradeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $submissions = AssignmentSubmission::whereNotNull('grade')->get();

        if ($submissions->isEmpty()) {
            $this->command->warn('No graded submissions found. Please run AssignmentSeeder first!');
            return;
        }

        foreach ($submissions as $submission) {
            $assignment = $submission->assignment;
            $student = $submission->student;
            
            if (!$assignment || !$student) {
                continue;
            }

            $class = $assignment->classModel;
            $category = ['Homework', 'Quiz', 'Project', 'Exam'][rand(0, 3)];
            
            Grade::create([
                'student_id' => $student->id,
                'assignment_id' => $assignment->id,
                'class_id' => $assignment->class_id,
                'subject' => $class ? $class->category : 'General',
                'assessment' => $assignment->title, // Use assignment title as assessment name
                'grade' => $submission->grade,
                'max_grade' => $assignment->max_points, // Use max_points from assignment as max_grade
                'category' => $category,
                'date' => $submission->graded_at ?? $submission->submitted_at ?? now(), // Use graded_at or submitted_at as date
                'notes' => $submission->feedback ?? null,
            ]);
        }

        $this->command->info('Grades seeded successfully!');
        $this->command->info('Total: ' . Grade::count() . ' grades created');
    }
}

