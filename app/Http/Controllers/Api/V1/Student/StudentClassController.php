<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\ClassModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentClassController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        $query = $student->classes()
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

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        $class = $student->classes()
            ->with([
                'tutor.user', 
                'schedules', 
                'assignments', 
                'resources',
                'sessions' => function($query) {
                    $query->orderBy('date', 'desc')
                          ->orderBy('start_time', 'desc');
                }
            ])
            ->findOrFail($id);

        // Format response with lessons and materials
        $classData = $class->toArray();
        $classData['lessons'] = $class->sessions->map(function($session) {
            return [
                'id' => $session->id,
                'date' => $session->date,
                'start_time' => $session->start_time,
                'end_time' => $session->end_time,
                'subject' => $session->subject,
                'topics_taught' => $session->topics_taught,
                'lesson_note' => $session->lesson_note,
                'homework_resources' => $session->homework_resources,
                'status' => $session->status,
                'location' => $session->location,
            ];
        });
        $classData['materials'] = $class->resources->map(function($resource) {
            return [
                'id' => $resource->id,
                'title' => $resource->title,
                'description' => $resource->description,
                'type' => $resource->type,
                'category' => $resource->category,
                'url' => $resource->url,
                'file_path' => $resource->file_path,
                'file_size' => $resource->file_size,
                'downloads' => $resource->downloads,
                'created_at' => $resource->created_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $classData,
        ]);
    }

    public function enroll(Request $request, $id)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        $class = ClassModel::findOrFail($id);

        // Check if already enrolled
        if ($student->classes()->where('classes.id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Already enrolled in this class',
            ], 400);
        }

        // Check capacity
        if ($class->capacity && $class->enrolled >= $class->capacity) {
            return response()->json([
                'success' => false,
                'message' => 'Class is full',
            ], 400);
        }

        // Enroll student
        $student->classes()->attach($id);

        // Update enrolled count
        $class->increment('enrolled');

        return response()->json([
            'success' => true,
            'data' => $class->load(['tutor.user', 'schedules']),
            'message' => 'Enrolled successfully',
        ]);
    }

    public function unenroll(Request $request, $id)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        $class = ClassModel::findOrFail($id);

        // Check if enrolled
        if (!$student->classes()->where('classes.id', $id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Not enrolled in this class',
            ], 400);
        }

        // Unenroll student
        $student->classes()->detach($id);

        // Update enrolled count
        $class->decrement('enrolled');

        return response()->json([
            'success' => true,
            'message' => 'Unenrolled successfully',
        ]);
    }
}

