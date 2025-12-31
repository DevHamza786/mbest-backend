<?php

namespace App\Http\Controllers\Api\V1\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Question;
use App\Models\Tutor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TutorQuestionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $query = Question::where('tutor_id', $tutor->id)
            ->with(['student.user', 'assignment', 'classModel', 'attachments'])
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
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $question = Question::where('tutor_id', $tutor->id)
            ->with(['student.user', 'assignment', 'classModel', 'attachments'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $question,
        ]);
    }

    public function reply(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'answer' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $question = Question::where('tutor_id', $tutor->id)
            ->findOrFail($id);

        $question->update([
            'answer' => $request->answer,
            'status' => 'answered',
            'answered_at' => now(),
        ]);

        $question->load(['student.user', 'assignment', 'classModel', 'attachments']);

        return response()->json([
            'success' => true,
            'message' => 'Answer submitted successfully',
            'data' => $question,
        ]);
    }

    public function updateStatus(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,answered,closed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $question = Question::where('tutor_id', $tutor->id)
            ->findOrFail($id);

        $question->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Status updated successfully',
            'data' => $question,
        ]);
    }
}
