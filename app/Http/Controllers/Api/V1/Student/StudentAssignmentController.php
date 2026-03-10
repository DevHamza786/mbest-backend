<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StudentAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        $query = Assignment::whereHas('classModel.students', function ($q) use ($student) {
            $q->where('students.id', $student->id);
        })
        ->with([
            'classModel', 
            'tutor.user', 
            'submissions' => function ($q) use ($student) {
                $q->where('student_id', $student->id);
            }
        ]);

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'due') {
                $query->whereDoesntHave('submissions', function ($q) use ($student) {
                        $q->where('student_id', $student->id)
                          ->where('status', 'submitted');
                    });
            } elseif ($request->status === 'submitted') {
                $query->whereHas('submissions', function ($q) use ($student) {
                    $q->where('student_id', $student->id)
                      ->where('status', 'submitted');
                });
            } elseif ($request->status === 'graded') {
                $query->whereHas('submissions', function ($q) use ($student) {
                    $q->where('student_id', $student->id)
                      ->whereNotNull('grade');
                });
            }
        }

        // Filter by class
        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        $perPage = $request->get('per_page', 15);
        $assignments = $query->orderBy('due_date')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $assignments,
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        $assignment = Assignment::whereHas('classModel.students', function ($q) use ($student) {
            $q->where('students.id', $student->id);
        })
        ->with([
            'classModel',
            'classModel.tutor.user',
            'tutor.user', 
            'submissions' => function ($q) use ($student) {
                $q->where('student_id', $student->id);
            }
        ])
        ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $assignment,
        ]);
    }

    public function submit(Request $request, $id)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        $assignment = Assignment::whereHas('classModel.students', function ($q) use ($student) {
            $q->where('students.id', $student->id);
        })->findOrFail($id);

        // Check if already submitted and if we can edit
        $existingSubmission = AssignmentSubmission::where('assignment_id', $id)
            ->where('student_id', $student->id)
            ->first();

        // Check if due date has passed
        $isPastDue = $assignment->due_date < now();
        
        // If submission exists and due date passed, don't allow editing
        if ($existingSubmission && $isPastDue) {
            return response()->json([
                'success' => false,
                'message' => 'Submission closed. Cannot edit after due date.',
            ], 400);
        }

        // Validate submission based on assignment's configured submission type
        $rules = [];

        if ($assignment->submission_type === 'file') {
            $rules['file'] = 'required|file|max:10240';
        } elseif ($assignment->submission_type === 'text') {
            $rules['text_submission'] = 'required|string';
        } elseif ($assignment->submission_type === 'link') {
            $rules['link_submission'] = 'required|url';
        }

        $validated = $request->validate($rules);

        $submissionData = [
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'status' => 'submitted',
            'submitted_at' => now(),
        ];

        // Handle file submission
        if ($assignment->submission_type === 'file' && $request->hasFile('file')) {
            // If re-submitting, delete old file if it exists
            if ($existingSubmission && $existingSubmission->file_url) {
                Storage::disk('public')->delete($existingSubmission->file_url);
            }

            $file = $request->file('file');

            // Keep original filename (optionally prefixed to avoid collisions)
            $originalName = $file->getClientOriginalName();
            $storedName = time() . '_' . $originalName;

            $path = $file->storeAs('assignments/submissions', $storedName, 'public');

            // Save stored path (includes original filename) so frontend can display it
            $submissionData['file_url'] = $path;
        }

        // Handle text submission
        if ($assignment->submission_type === 'text' && isset($validated['text_submission'])) {
            $submissionData['text_submission'] = $validated['text_submission'];
        }

        // Handle link submission
        if ($assignment->submission_type === 'link' && isset($validated['link_submission'])) {
            $submissionData['link_submission'] = $validated['link_submission'];
        }

        if ($existingSubmission) {
            $existingSubmission->update($submissionData);
            $submission = $existingSubmission;
        } else {
            $submission = AssignmentSubmission::create($submissionData);
        }

        return response()->json([
            'success' => true,
            'data' => $submission->load(['assignment']),
            'message' => 'Assignment submitted successfully',
        ], 201);
    }

    public function getSubmission(Request $request, $id)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        $assignment = Assignment::whereHas('classModel.students', function ($q) use ($student) {
            $q->where('students.id', $student->id);
        })->findOrFail($id);

        $submission = AssignmentSubmission::where('assignment_id', $id)
            ->where('student_id', $student->id)
            ->with(['assignment'])
            ->first();

        if (!$submission) {
            return response()->json([
                'success' => false,
                'message' => 'No submission found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $submission,
        ]);
    }
}

