<?php

namespace App\Http\Controllers\Api\V1\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Tutor;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use Illuminate\Http\Request;

class TutorAssignmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        // Calculate statistics
        $totalAssignments = Assignment::where('tutor_id', $tutor->id)->count();
        $publishedAssignments = Assignment::where('tutor_id', $tutor->id)->where('status', 'published')->count();
        $draftAssignments = Assignment::where('tutor_id', $tutor->id)->where('status', 'draft')->count();
        
        // Count pending grading (submissions that are submitted but not graded)
        $pendingGrading = AssignmentSubmission::whereHas('assignment', function ($q) use ($tutor) {
            $q->where('tutor_id', $tutor->id);
        })->where('status', 'submitted')->count();

        $query = Assignment::where('tutor_id', $tutor->id)
            ->with(['classModel', 'submissions']);

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        // Filter by status
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        // Filter by class
        if ($request->has('class_id') && $request->class_id !== 'all') {
            $query->where('class_id', $request->class_id);
        }

        // Filter for assignments needing grading
        if ($request->has('needs_grading') && $request->needs_grading == 'true') {
            $query->whereHas('submissions', function ($q) {
                $q->where('status', 'submitted');
            });
        }

        $perPage = $request->get('per_page', 15);
        $assignments = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Transform assignments to include submission counts
        $transformedAssignments = $assignments->getCollection()->map(function ($assignment) {
            $submissions = $assignment->submissions;
            $submittedCount = $submissions->where('status', '!=', 'pending')->count();
            $totalStudents = $assignment->classModel ? $assignment->classModel->enrolled : 0;
            
            $assignmentArray = $assignment->toArray();
            $assignmentArray['submissions_count'] = $submittedCount;
            $assignmentArray['total_students'] = $totalStudents;
            
            return $assignmentArray;
        });

        // Replace the data in paginator with transformed assignments
        $assignments->setCollection($transformedAssignments);

        // Convert paginator to array and add statistics
        $responseData = $assignments->toArray();
        $responseData['statistics'] = [
            'total' => $totalAssignments,
            'published' => $publishedAssignments,
            'drafts' => $draftAssignments,
            'pending_grading' => $pendingGrading,
        ];

        return response()->json([
            'success' => true,
            'data' => $responseData,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'class_id' => 'nullable|exists:classes,id',
            'due_date' => 'required|date',
            'max_points' => 'required|integer|min:1',
            'submission_type' => 'required|in:file,text,link',
            'allowed_file_types' => 'nullable|array',
            'status' => 'sometimes|in:draft,published,archived',
        ]);

        $assignment = Assignment::create([
            'tutor_id' => $tutor->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
            'class_id' => $validated['class_id'] ?? null,
            'due_date' => $validated['due_date'],
            'max_points' => $validated['max_points'],
            'submission_type' => $validated['submission_type'],
            'allowed_file_types' => $validated['allowed_file_types'] ?? null,
            'status' => $validated['status'] ?? 'draft',
        ]);

        return response()->json([
            'success' => true,
            'data' => $assignment->load(['classModel', 'submissions']),
            'message' => 'Assignment created successfully',
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $assignment = Assignment::where('tutor_id', $tutor->id)
            ->with(['classModel', 'submissions.student.user'])
            ->findOrFail($id);

        // Transform assignment data for frontend
        $assignmentData = $assignment->toArray();
        $assignmentData['submissions_count'] = $assignment->submissions->count();
        $assignmentData['total_students'] = $assignment->classModel ? $assignment->classModel->enrolled : 0;

        return response()->json([
            'success' => true,
            'data' => $assignmentData,
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $assignment = Assignment::where('tutor_id', $tutor->id)->findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'class_id' => 'nullable|exists:classes,id',
            'due_date' => 'sometimes|date',
            'max_points' => 'sometimes|integer|min:1',
            'submission_type' => 'sometimes|in:file,text,link',
            'allowed_file_types' => 'nullable|array',
            'status' => 'sometimes|in:draft,published,archived',
        ]);

        $assignment->update($validated);

        return response()->json([
            'success' => true,
            'data' => $assignment->load(['classModel']),
            'message' => 'Assignment updated successfully',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $assignment = Assignment::where('tutor_id', $tutor->id)->findOrFail($id);
        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Assignment deleted successfully',
        ]);
    }

    public function submissions(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $assignment = Assignment::where('tutor_id', $tutor->id)->findOrFail($id);
        $submissions = $assignment->submissions()->with('student.user')->orderBy('submitted_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $submissions,
        ]);
    }

    public function grade(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $submission = AssignmentSubmission::whereHas('assignment', function ($q) use ($tutor) {
            $q->where('tutor_id', $tutor->id);
        })->findOrFail($id);

        $validated = $request->validate([
            'grade' => 'required|numeric|min:0|max:' . $submission->assignment->max_points,
            'feedback' => 'nullable|string|max:1000',
        ]);

        $submission->update([
            'grade' => $validated['grade'],
            'feedback' => $validated['feedback'] ?? null,
            'status' => 'graded',
            'graded_at' => now(),
        ]);

        // Create or update grade record
        $grade = \App\Models\Grade::updateOrCreate(
            [
                'student_id' => $submission->student_id,
                'assignment_id' => $submission->assignment_id,
            ],
            [
                'grade' => $validated['grade'],
                'max_points' => $submission->assignment->max_points,
                'class_id' => $submission->assignment->class_id,
                'subject' => $submission->assignment->classModel->subject ?? 'General',
                'graded_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $submission->load(['student.user', 'assignment']),
            'message' => 'Submission graded successfully',
        ]);
    }
}

