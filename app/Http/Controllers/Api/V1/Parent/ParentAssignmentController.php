<?php

namespace App\Http\Controllers\Api\V1\Parent;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\ParentModel;
use Illuminate\Http\Request;

class ParentAssignmentController extends Controller
{
    public function index(Request $request, $id)
    {
        $user = $request->user();
        $parent = $user->parentModel;

        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'Parent profile not found',
            ], 404);
        }

        $child = $parent->students()->findOrFail($id);

        $query = Assignment::whereHas('classModel.students', function ($q) use ($child) {
            $q->where('students.id', $child->id);
        })
        ->with(['classModel', 'tutor.user', 'submissions' => function ($q) use ($child) {
            $q->where('student_id', $child->id);
        }]);

        // Filter by status
        if ($request->has('status')) {
            if ($request->status === 'due') {
                $query->where('due_date', '>=', now())
                      ->where('status', 'published')
                      ->whereDoesntHave('submissions', function ($q) use ($child) {
                          $q->where('student_id', $child->id)
                            ->where('status', 'submitted');
                      });
            } elseif ($request->status === 'submitted') {
                $query->whereHas('submissions', function ($q) use ($child) {
                    $q->where('student_id', $child->id)
                      ->where('status', 'submitted');
                });
            } elseif ($request->status === 'graded') {
                $query->whereHas('submissions', function ($q) use ($child) {
                    $q->where('student_id', $child->id)
                      ->where('status', 'graded');
                });
            }
        }

        // Filter by class
        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $assignments = $query->orderBy('due_date', 'asc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $assignments,
        ]);
    }

    public function show(Request $request, $childId, $assignmentId)
    {
        $user = $request->user();
        $parent = $user->parentModel;

        if (!$parent) {
            return response()->json([
                'success' => false,
                'message' => 'Parent profile not found',
            ], 404);
        }

        $child = $parent->students()->findOrFail($childId);

        $assignment = Assignment::whereHas('classModel.students', function ($q) use ($child) {
            $q->where('students.id', $child->id);
        })
        ->with([
            'classModel',
            'tutor.user',
            'submissions' => function ($q) use ($child) {
                $q->where('student_id', $child->id);
            }
        ])
        ->findOrFail($assignmentId);

        return response()->json([
            'success' => true,
            'data' => $assignment,
        ]);
    }
}

