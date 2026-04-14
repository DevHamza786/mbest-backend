<?php

namespace App\Http\Controllers\Api\V1\Parent;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Student;
use App\Models\ClassModel;
use App\Services\StudentService;
use Illuminate\Http\Request;

class ParentClassController extends Controller
{
    protected $studentService;

    public function __construct(StudentService $studentService)
    {
        $this->studentService = $studentService;
    }

    public function index(Request $request, $id)
    {
        $user = $request->user();
        
        // Get student - check if exists first
        $child = Student::find($id);

        if (!$child) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found',
            ], 404);
        }
        
        // Verify ownership - check both parent_id and pivot table
        $isOwner = ($child->parent_id === $user->id) || 
                   $child->parents()->where('users.id', $user->id)->exists();
        
        if (!$isOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found or access denied',
            ], 404);
        }

        $query = $child->classes()
            ->with(['tutor.user', 'schedules']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $perPage = $request->get('per_page', 15);
        $classes = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $classes,
        ]);
    }

    public function show(Request $request, $childId, $classId)
    {
        $user = $request->user();
        
        // Get student - check if exists first
        $child = Student::find($childId);
        
        if (!$child) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found',
            ], 404);
        }
        
        // Verify ownership - check both parent_id and pivot table
        $isOwner = ($child->parent_id === $user->id) || 
                   $child->parents()->where('users.id', $user->id)->exists();
        
        if (!$isOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found or access denied',
            ], 404);
        }
        
        $class = $child->classes()
            ->with(['tutor.user', 'schedules', 'assignments', 'resources'])
            ->findOrFail($classId);

        return response()->json([
            'success' => true,
            'data' => $class,
        ]);
    }

    /**
     * Get available classes for enrollment (classes in package that student is not enrolled in)
     */
    public function getAvailableClasses(Request $request, $id)
    {
        $user = $request->user();
        
        // Get student - check if exists first
        $child = Student::find($id);
        
        if (!$child) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found',
            ], 404);
        }
        
        // Verify ownership - check both parent_id and pivot table
        $isOwner = ($child->parent_id === $user->id) || 
                   $child->parents()->where('users.id', $user->id)->exists();
        
        if (!$isOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found or access denied',
            ], 404);
        }

        // Get parent's package classes
        if (!$user->package_id) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'No subscription package found',
            ]);
        }

        $package = $user->package;
        if (!$package) {
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Package not found',
            ]);
        }

        // Get classes in package that student is not enrolled in
        $enrolledClassIds = $child->classes()->pluck('classes.id')->toArray();
        $availableClasses = $package->classes()
            ->whereNotIn('classes.id', $enrolledClassIds)
            ->with(['tutor.user', 'schedules'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $availableClasses,
        ]);
    }

    /**
     * Enroll child in a class
     */
    public function enroll(Request $request, $childId, $classId)
    {
        $user = $request->user();
        
        // Get student - check if exists first
        $child = Student::find($childId);
        
        if (!$child) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found',
            ], 404);
        }
        
        // Verify ownership - check both parent_id and pivot table
        $isOwner = ($child->parent_id === $user->id) || 
                   $child->parents()->where('users.id', $user->id)->exists();
        
        if (!$isOwner) {
            return response()->json([
                'success' => false,
                'message' => 'Student not found or access denied',
            ], 404);
        }
        $class = ClassModel::findOrFail($classId);

        try {
            // Use StudentService to enroll (handles validation and notifications)
            $this->studentService->enrollStudentInClass($child, $class);

            return response()->json([
                'success' => true,
                'data' => $class->load(['tutor.user', 'schedules']),
                'message' => 'Student enrolled successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}

