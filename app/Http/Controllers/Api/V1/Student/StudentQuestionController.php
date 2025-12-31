<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\QuestionAttachment;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class StudentQuestionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        $query = Question::where('student_id', $student->id)
            ->with(['tutor.user', 'assignment', 'classModel', 'attachments'])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by assignment
        if ($request->has('assignment_id')) {
            $query->where('assignment_id', $request->assignment_id);
        }

        $perPage = $request->get('per_page', 15);
        $questions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $questions,
        ]);
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        $question = Question::where('student_id', $student->id)
            ->with(['tutor.user', 'assignment', 'classModel', 'attachments'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $question,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $student = Student::where('user_id', $user->id)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'question' => 'required|string',
            'priority' => 'nullable|in:low,medium,high',
            'category' => 'nullable|in:assignment,concept,technical,grading,general',
            'assignment_id' => 'nullable|exists:assignments,id',
            'class_id' => 'nullable|exists:classes,id',
            'tutor_id' => 'nullable|exists:tutors,id',
            'attachments' => 'nullable|array|max:3',
            'attachments.*' => 'file|max:10240', // 10MB max per file
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Determine tutor_id if not provided
        $tutorId = $request->tutor_id;
        if (!$tutorId && $request->assignment_id) {
            $assignment = \App\Models\Assignment::find($request->assignment_id);
            $tutorId = $assignment?->tutor_id;
        }
        if (!$tutorId && $request->class_id) {
            $class = \App\Models\ClassModel::find($request->class_id);
            $tutorId = $class?->tutor_id;
        }

        $question = Question::create([
            'student_id' => $student->id,
            'tutor_id' => $tutorId,
            'assignment_id' => $request->assignment_id,
            'class_id' => $request->class_id,
            'subject' => $request->subject,
            'question' => $request->question,
            'priority' => $request->priority ?? 'medium',
            'category' => $request->category ?? 'general',
            'status' => 'pending',
        ]);

        // Handle file attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('question-attachments', 'public');
                QuestionAttachment::create([
                    'question_id' => $question->id,
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'file_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        $question->load(['tutor.user', 'assignment', 'classModel', 'attachments']);

        return response()->json([
            'success' => true,
            'message' => 'Question submitted successfully',
            'data' => $question,
        ], 201);
    }
}
