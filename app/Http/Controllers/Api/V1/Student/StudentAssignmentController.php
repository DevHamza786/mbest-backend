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
        // due_date is stored as a datetime (usually at 00:00 when using date-only input).
        // Treat it as "past due" only after the due date ends.
        $isPastDue = $assignment->due_date->lt(now()->startOfDay());

    // If the tutor already graded this submission, the student must not be able to edit it.
    $isGraded = $existingSubmission && (
        $existingSubmission->status === 'graded' ||
        $existingSubmission->grade !== null
    );
        
    // If submission exists and due date passed, don't allow editing
    // Also block edits after grading even if due_date hasn't passed yet.
    if ($existingSubmission && ($isPastDue || $isGraded)) {
            return response()->json([
                'success' => false,
            'message' => $isGraded
                ? 'Submission graded. Cannot edit after grading.'
                : 'Submission closed. Cannot edit after due date.',
            ], 400);
        }

        // Validate submission based on assignment's configured submission type
        $rules = [];

        if ($assignment->submission_type === 'file') {
            $rules['file'] = 'required|file|max:10240';
            $rules['student_comment'] = 'nullable|string|max:1000';
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

        if ($assignment->submission_type === 'file') {
            $comment = $validated['student_comment'] ?? null;
            $submissionData['student_comment'] = ($comment !== '' ? $comment : null);
        }

        // Handle file submission
        if ($assignment->submission_type === 'file' && $request->hasFile('file')) {
            // If re-submitting, delete old file if it exists. Use the raw stored
            // path, not the file_url accessor (which now resolves to an absolute
            // URL) - Storage::delete() expects a disk-relative path.
            $oldPath = $existingSubmission?->getRawOriginal('file_url');
            if ($oldPath) {
                Storage::disk('public')->delete($oldPath);
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

