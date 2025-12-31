<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ClassModel;
use App\Models\Tutor;
use App\Models\Student;
use Illuminate\Database\Seeder;

class AssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classes = ClassModel::all();
        $tutors = Tutor::all();
        $students = Student::all();

        if ($classes->isEmpty() || $tutors->isEmpty() || $students->isEmpty()) {
            $this->command->warn('Please run UserSeeder and ClassSeeder first!');
            return;
        }

        $assignmentTitles = [
            'Algebra Homework Set 1',
            'Calculus Practice Problems',
            'Web Development Project',
            'Physics Lab Report',
            'English Essay Assignment',
            'Chemistry Problem Set',
            'Data Structures Exercise',
            'Literature Analysis',
            'Organic Chemistry Quiz',
            'Programming Challenge',
        ];

        $submissionTypes = ['file', 'text', 'link'];
        $statuses = ['draft', 'published', 'archived'];

        foreach ($classes as $class) {
            // Create 3-5 assignments per class
            $assignmentCount = rand(3, 5);
            
            for ($i = 0; $i < $assignmentCount; $i++) {
                $tutor = $tutors->where('id', $class->tutor_id)->first() ?? $tutors->random();
                $title = $assignmentTitles[array_rand($assignmentTitles)] . ' - ' . $class->name;
                $dueDate = now()->addDays(rand(7, 30));
                $status = $statuses[array_rand($statuses)];

                $assignment = Assignment::create([
                    'tutor_id' => $tutor->id,
                    'class_id' => $class->id,
                    'title' => $title,
                    'description' => 'Complete the following exercises and submit your work. Show all steps and provide detailed explanations.',
                    'instructions' => '1. Read the problem carefully\n2. Show all your work\n3. Submit before the deadline\n4. Include your name and student ID',
                    'due_date' => $dueDate,
                    'max_points' => rand(50, 100),
                    'submission_type' => $submissionTypes[array_rand($submissionTypes)],
                    'allowed_file_types' => ['pdf', 'doc', 'docx'],
                    'status' => $status,
                ]);

                // Create submissions for published assignments
                if ($status === 'published') {
                    $classStudents = $class->students;
                    
                    foreach ($classStudents as $student) {
                        // 70% chance of submission
                        if (rand(0, 10) < 7) {
                            $submittedAt = $dueDate->copy()->subDays(rand(0, 5));
                            $isGraded = rand(0, 10) < 6;
                            
                            $submission = AssignmentSubmission::create([
                                'assignment_id' => $assignment->id,
                                'student_id' => $student->id,
                                'submitted_at' => $submittedAt,
                                'file_url' => $assignment->submission_type === 'file' ? 'assignments/submissions/file_' . $student->id . '_' . $assignment->id . '.pdf' : null,
                                'text_submission' => $assignment->submission_type === 'text' ? 'This is my submission for ' . $assignment->title . '. I have completed all the required tasks.' : null,
                                'link_submission' => $assignment->submission_type === 'link' ? 'https://example.com/submission/' . $student->id : null,
                                'status' => $isGraded ? 'graded' : 'submitted',
                                'grade' => $isGraded ? rand(60, 100) : null,
                                'feedback' => $isGraded ? ['Good work!', 'Well done!', 'Needs improvement', 'Excellent effort'][rand(0, 3)] : null,
                                'graded_at' => $isGraded ? $submittedAt->addHours(rand(1, 48)) : null,
                            ]);
                        }
                    }
                }
            }
        }

        $this->command->info('Assignments seeded successfully!');
        $this->command->info('Total: ' . Assignment::count() . ' assignments created');
        $this->command->info('Total: ' . AssignmentSubmission::count() . ' submissions created');
    }
}

