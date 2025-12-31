<?php

namespace App\Http\Controllers\Api\V1\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Tutor;
use App\Models\Student;
use App\Models\Grade;
use App\Models\ClassModel;
use App\Models\Assignment;
use Illuminate\Http\Request;

class TutorStudentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $query = Student::whereHas('classes', function ($q) use ($tutor) {
            $q->where('tutor_id', $tutor->id);
        })
        ->with(['user', 'classes' => function ($q) use ($tutor) {
            $q->where('tutor_id', $tutor->id);
        }]);

        // Filter by class
        if ($request->has('class_id')) {
            $query->whereHas('classes', function ($q) use ($request) {
                $q->where('classes.id', $request->class_id);
            });
        }

        // Search by name or email
        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $students = $query->paginate($perPage);

        // Add additional stats for each student
        $students->getCollection()->transform(function ($student) use ($tutor) {
            $student->overall_grade = Grade::where('student_id', $student->id)
                ->whereHas('assignment', function ($q) use ($tutor) {
                    $q->where('tutor_id', $tutor->id);
                })
                ->avg('grade') ?? 0;

            $student->total_assignments = $student->assignments()
                ->whereHas('assignment', function ($q) use ($tutor) {
                    $q->where('tutor_id', $tutor->id);
                })
                ->count();

            $student->completed_assignments = $student->assignments()
                ->whereHas('assignment', function ($q) use ($tutor) {
                    $q->where('tutor_id', $tutor->id);
                })
                ->where('status', 'submitted')
                ->count();

            return $student;
        });

        return response()->json([
            'success' => true,
            'data' => $students,
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $student = Student::whereHas('classes', function ($q) use ($tutor) {
            $q->where('tutor_id', $tutor->id);
        })
        ->with(['user', 'classes' => function ($q) use ($tutor) {
            $q->where('tutor_id', $tutor->id)->with('tutor');
        }])
        ->findOrFail($id);

        // Add detailed stats
        $student->overall_grade = Grade::where('student_id', $student->id)
            ->whereHas('assignment', function ($q) use ($tutor) {
                $q->where('tutor_id', $tutor->id);
            })
            ->avg('grade') ?? 0;

        $student->total_assignments = $student->assignments()
            ->whereHas('assignment', function ($q) use ($tutor) {
                $q->where('tutor_id', $tutor->id);
            })
            ->count();

        $student->completed_assignments = $student->assignments()
            ->whereHas('assignment', function ($q) use ($tutor) {
                $q->where('tutor_id', $tutor->id);
            })
            ->where('status', 'submitted')
            ->count();

        $student->pending_assignments = $student->assignments()
            ->whereHas('assignment', function ($q) use ($tutor) {
                $q->where('tutor_id', $tutor->id)
                  ->where('status', 'published')
                  ->where('due_date', '>=', now());
            })
            ->where('status', '!=', 'submitted')
            ->count();

        return response()->json([
            'success' => true,
            'data' => $student,
        ]);
    }

    public function grades(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $student = Student::whereHas('classes', function ($q) use ($tutor) {
            $q->where('tutor_id', $tutor->id);
        })->findOrFail($id);

        $query = Grade::where('student_id', $student->id)
            ->whereHas('assignment', function ($q) use ($tutor) {
                $q->where('tutor_id', $tutor->id);
            })
            ->with(['assignment', 'classModel']);

        // Filter by class
        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        // Filter by subject
        if ($request->has('subject')) {
            $query->where('subject', $request->subject);
        }

        $perPage = $request->get('per_page', 15);
        $grades = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Add statistics
        $stats = [
            'average_grade' => Grade::where('student_id', $student->id)
                ->whereHas('assignment', function ($q) use ($tutor) {
                    $q->where('tutor_id', $tutor->id);
                })
                ->avg('grade') ?? 0,
            'total_grades' => $grades->total(),
            'highest_grade' => Grade::where('student_id', $student->id)
                ->whereHas('assignment', function ($q) use ($tutor) {
                    $q->where('tutor_id', $tutor->id);
                })
                ->max('grade') ?? 0,
            'lowest_grade' => Grade::where('student_id', $student->id)
                ->whereHas('assignment', function ($q) use ($tutor) {
                    $q->where('tutor_id', $tutor->id);
                })
                ->min('grade') ?? 0,
        ];

        return response()->json([
            'success' => true,
            'data' => $grades,
            'stats' => $stats,
        ]);
    }

    public function assignments(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $student = Student::whereHas('classes', function ($q) use ($tutor) {
            $q->where('tutor_id', $tutor->id);
        })->findOrFail($id);

        // Get all classes the student is enrolled in that belong to this tutor
        $classIds = $student->classes()
            ->where('tutor_id', $tutor->id)
            ->pluck('classes.id')
            ->toArray();

        if (empty($classIds)) {
            return response()->json([
                'success' => true,
                'data' => [],
            ]);
        }

        // Get all assignments from these classes
        $assignments = Assignment::whereIn('class_id', $classIds)
            ->with(['classModel', 'submissions' => function ($q) use ($id) {
                $q->where('student_id', $id);
            }])
            ->orderBy('due_date', 'desc')
            ->get();

        // Transform assignments to include submission info
        $assignments->transform(function ($assignment) use ($id) {
            $submission = $assignment->submissions->first();
            $assignment->submission = $submission ? [
                'id' => $submission->id,
                'status' => $submission->status,
                'grade' => $submission->grade,
                'feedback' => $submission->feedback,
                'submitted_at' => $submission->submitted_at,
            ] : null;
            unset($assignment->submissions);
            return $assignment;
        });

        return response()->json([
            'success' => true,
            'data' => $assignments,
        ]);
    }

    public function recipients(Request $request)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        // Get all students in tutor's classes
        $students = Student::whereHas('classes', function ($q) use ($tutor) {
            $q->where('tutor_id', $tutor->id);
        })
        ->with(['user', 'parents'])
        ->get();

        $recipients = [];

        // Add students as recipients
        foreach ($students as $student) {
            $recipients[] = [
                'id' => $student->user->id,
                'name' => $student->user->name,
                'email' => $student->user->email,
                'role' => 'student',
                'avatar' => $student->user->avatar ?? null,
            ];
        }

        // Add parents as recipients (unique parents only)
        $parentIds = [];
        foreach ($students as $student) {
            foreach ($student->parents as $parent) {
                if (!in_array($parent->id, $parentIds)) {
                    $parentIds[] = $parent->id;
                    $recipients[] = [
                        'id' => $parent->id,
                        'name' => $parent->name,
                        'email' => $parent->email,
                        'role' => 'parent',
                        'avatar' => $parent->avatar ?? null,
                    ];
                }
            }
        }

        // Sort by name
        usort($recipients, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return response()->json([
            'success' => true,
            'data' => $recipients,
        ]);
    }
}

