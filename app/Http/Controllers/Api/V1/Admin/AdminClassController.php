<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassModel;
use Illuminate\Http\Request;

class AdminClassController extends Controller
{
    public function index(Request $request)
    {
        $query = ClassModel::with(['tutor.user', 'students.user', 'packages']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by tutor
        if ($request->has('tutor_id')) {
            $query->where('tutor_id', $request->tutor_id);
        }

        if ($request->filled('level')) {
            $query->where('level', $request->input('level'));
        }

        if ($request->filled('category')) {
            $query->where('category', 'like', '%'.$request->input('category').'%');
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('per_page', 15);
        $classes = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $classes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:classes',
            'tutor_id' => 'required|exists:tutors,id',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'level' => 'nullable|in:Beginner,Intermediate,Advanced',
            'year_level' => 'nullable|string|max:50',
            'capacity' => 'nullable|integer|min:1',
            'credits' => 'nullable|integer',
            'duration' => 'nullable|string|max:50',
            'status' => 'sometimes|in:draft,active,archived',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        if (array_key_exists('status', $validated)) {
            $validated['status'] = $this->mapClassStatusToDb($validated['status']);
        }

        $class = ClassModel::create($validated);

        return response()->json([
            'success' => true,
            'data' => $class->load(['tutor.user', 'students.user']),
            'message' => 'Class created successfully',
        ], 201);
    }

    public function show($id)
    {
        $class = ClassModel::with([
            'tutor.user', 
            'students.user', 
            'schedules', 
            'assignments.submissions.student.user',
            'resources'
        ])->findOrFail($id);

        // Transform assignments to include submission stats
        $class->assignments->transform(function ($assignment) {
            $assignment->submissions_count = $assignment->submissions->count();
            $assignment->graded_count = $assignment->submissions->whereNotNull('grade')->count();
            return $assignment;
        });

        return response()->json([
            'success' => true,
            'data' => $class,
        ]);
    }

    public function update(Request $request, $id)
    {
        $class = ClassModel::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:50|unique:classes,code,' . $id,
            'tutor_id' => 'sometimes|exists:tutors,id',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
            'level' => 'nullable|in:Beginner,Intermediate,Advanced',
            'year_level' => 'nullable|string|max:50',
            'capacity' => 'nullable|integer|min:1',
            'credits' => 'nullable|integer',
            'duration' => 'nullable|string|max:50',
            'status' => 'sometimes|in:draft,active,archived',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
        ]);

        if (isset($validated['status'])) {
            $validated['status'] = $this->mapClassStatusToDb($validated['status']);
        }

        $class->update($validated);

        return response()->json([
            'success' => true,
            'data' => $class->load(['tutor.user', 'students.user']),
            'message' => 'Class updated successfully',
        ]);
    }

    /**
     * Map UI status (draft, active, archived) to DB enum (inactive, active, completed).
     */
    private function mapClassStatusToDb(?string $status): string
    {
        return match ($status) {
            'draft' => 'inactive',
            'archived' => 'completed',
            default => 'active',
        };
    }

    public function destroy($id)
    {
        $class = ClassModel::findOrFail($id);

        if ($class->students()->exists() || (int) $class->enrolled > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a class that has enrolled students.',
            ], 422);
        }

        $class->delete();

        return response()->json([
            'success' => true,
            'message' => 'Class deleted successfully',
        ]);
    }
}

