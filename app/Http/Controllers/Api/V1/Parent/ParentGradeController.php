<?php

namespace App\Http\Controllers\Api\V1\Parent;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\Grade;
use App\Models\ParentModel;
use Illuminate\Http\Request;

class ParentGradeController extends Controller
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

        $query = Grade::where('student_id', $child->id)
            ->with(['assignment', 'classModel']);

        // Filter by class
        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        // Filter by subject
        if ($request->has('subject')) {
            $query->where('subject', $request->subject);
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhereHas('assignment', function ($q) use ($search) {
                      $q->where('title', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = $request->get('per_page', 15);
        $grades = $query->orderBy('date', 'desc')->paginate($perPage);

        // Calculate statistics
        $stats = [
            'overall_average' => Grade::where('student_id', $child->id)->avg('grade') ?? 0,
            'total_grades' => Grade::where('student_id', $child->id)->count(),
            'highest_grade' => Grade::where('student_id', $child->id)->max('grade') ?? 0,
            'lowest_grade' => Grade::where('student_id', $child->id)->min('grade') ?? 0,
            'average_by_subject' => Grade::where('student_id', $child->id)
                ->selectRaw('subject, AVG(grade) as average')
                ->groupBy('subject')
                ->get()
                ->pluck('average', 'subject'),
        ];

        return response()->json([
            'success' => true,
            'data' => $grades,
            'stats' => $stats,
        ]);
    }
}

