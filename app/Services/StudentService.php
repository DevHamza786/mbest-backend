<?php

namespace App\Services;

use App\Models\Student;
use App\Models\User;
use App\Models\ClassModel;
use App\Models\Tutor;
use App\Models\Package;
use App\Services\NotificationService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class StudentService
{
    /**
     * Generate a unique student enrollment ID
     * Format: ENR-YYYY-XXXX (e.g., ENR-2025-0001)
     */
    public function generateEnrollmentId(): string
    {
        $year = date('Y');
        $prefix = "ENR-{$year}-";
        
        // Get the last enrollment ID for this year
        $lastStudent = Student::where('enrollment_id', 'like', "{$prefix}%")
            ->orderBy('enrollment_id', 'desc')
            ->first();
        
        if ($lastStudent && $lastStudent->enrollment_id) {
            // Extract the number part
            $parts = explode('-', $lastStudent->enrollment_id);
            $lastNumber = (int) end($parts);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return sprintf('%s%04d', $prefix, $newNumber);
    }

    /**
     * Create a student for a parent
     * Validates subscription limits and generates enrollment ID
     */
    public function createStudentForParent(User $parent, array $studentData): Student
    {
        // Check if parent has active subscription
        if ($parent->subscription_status !== 'active') {
            throw new \Exception('Parent does not have an active subscription');
        }

        // Check student limit
        if ($parent->hasReachedStudentLimit()) {
            throw new \Exception('Student limit reached for your subscription package');
        }

        // Create user account for student
        $user = User::create([
            'name' => $studentData['name'],
            'email' => $studentData['email'],
            'password' => bcrypt($studentData['password'] ?? Str::random(12)),
            'role' => 'student',
            'phone' => $studentData['phone'] ?? null,
            'date_of_birth' => $studentData['date_of_birth'] ?? null,
            'address' => $studentData['address'] ?? null,
            'is_active' => true,
        ]);

        // Generate enrollment ID
        $enrollmentId = $this->generateEnrollmentId();

        // Create student record
        $student = Student::create([
            'user_id' => $user->id,
            'parent_id' => $parent->id,
            'enrollment_id' => $enrollmentId,
            'grade' => $studentData['grade'] ?? null,
            'school' => $studentData['school'] ?? null,
            'emergency_contact_name' => $studentData['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $studentData['emergency_contact_phone'] ?? null,
        ]);

        // Link parent to student in pivot table
        \DB::table('parent_student')->insert([
            'parent_id' => $parent->id,
            'student_id' => $student->id,
            'relationship' => 'parent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Increment parent's student count
        $parent->increment('current_student_count');

        // Auto-enroll student in all classes included in the parent's subscription package
        if ($parent->package_id) {
            $package = Package::with(['classes.tutor.user'])->find($parent->package_id);
            if ($package && $package->classes && $package->classes->count() > 0) {
                $enrolledCount = 0;
                foreach ($package->classes as $class) {
                    try {
                        // Check if already enrolled (skip if already enrolled)
                        if (!$student->classes()->where('classes.id', $class->id)->exists()) {
                            // Enroll student directly (skip validation since we know it's in the package)
                            $student->classes()->attach($class->id);
                            $class->increment('enrolled');
                            $enrolledCount++;

                            // Notify tutor
                            if ($class->tutor && $class->tutor->user_id) {
                                $notificationService = new NotificationService();
                                $notificationService->createNotification(
                                    $class->tutor->user_id,
                                    'enrollment',
                                    'New Student Auto-Enrolled',
                                    "Student {$student->user->name} ({$student->enrollment_id}) has been automatically enrolled in your class '{$class->name}'",
                                    [
                                        'student_id' => $student->id,
                                        'class_id' => $class->id,
                                        'enrollment_id' => $student->enrollment_id,
                                    ],
                                    'medium'
                                );

                                // Optionally send email notification
                                if ($class->tutor->user && $class->tutor->user->email) {
                                    try {
                                        \Mail::to($class->tutor->user->email)->send(
                                            new \App\Mail\StudentEnrolledNotification($student, $class)
                                        );
                                    } catch (\Exception $e) {
                                        Log::warning('Failed to send enrollment email', [
                                            'tutor_email' => $class->tutor->user->email,
                                            'error' => $e->getMessage(),
                                        ]);
                                    }
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        // Log error but don't fail student creation if enrollment fails
                        Log::warning('Failed to auto-enroll student in class', [
                            'student_id' => $student->id,
                            'class_id' => $class->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                
                if ($enrolledCount > 0) {
                    Log::info('Auto-enrolled student in package classes', [
                        'student_id' => $student->id,
                        'parent_id' => $parent->id,
                        'package_id' => $package->id,
                        'classes_enrolled' => $enrolledCount,
                    ]);
                }
            }
        }

        return $student->load('user', 'classes');
    }

    /**
     * Enroll student in a class
     * Validates package includes class and notifies tutor
     */
    public function enrollStudentInClass(Student $student, ClassModel $class): void
    {
        $parent = $student->parent;
        
        if (!$parent) {
            throw new \Exception('Student has no parent');
        }

        // Check if parent's package includes this class
        if (!$parent->packageIncludesClass($class->id)) {
            throw new \Exception('This class is not included in your subscription package');
        }

        // Check if already enrolled
        if ($student->classes()->where('classes.id', $class->id)->exists()) {
            throw new \Exception('Student is already enrolled in this class');
        }

        // Enroll student
        $student->classes()->attach($class->id);
        $class->increment('enrolled');

        // Notify tutor
        if ($class->tutor && $class->tutor->user) {
            $notificationService = new NotificationService();
            $notificationService->createNotification(
                $class->tutor->user_id,
                'enrollment',
                'New Student Enrolled',
                "Student {$student->user->name} ({$student->enrollment_id}) has been enrolled in your class '{$class->name}'",
                [
                    'student_id' => $student->id,
                    'class_id' => $class->id,
                    'enrollment_id' => $student->enrollment_id,
                ],
                'medium'
            );

            // Optionally send email notification
            try {
                \Mail::to($class->tutor->user->email)->send(
                    new \App\Mail\StudentEnrolledNotification($student, $class)
                );
            } catch (\Exception $e) {
                Log::warning('Failed to send enrollment email', [
                    'tutor_email' => $class->tutor->user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
