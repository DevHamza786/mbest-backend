<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Traits\ParsesLessonRequests;
use Illuminate\Http\Request;

class AdminLessonRequestController extends Controller
{
    use ParsesLessonRequests;

    /**
     * Oversight view of lesson requests across all tutors. Approve/decline
     * remains a tutor action; admin can only monitor status here.
     */
    public function index(Request $request)
    {
        $query = $this->lessonRequestMessageQuery();

        $perPage = $request->get('per_page', 15);
        $messages = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $lessonRequests = $messages->getCollection()->map(
            fn ($message) => $this->transformLessonRequestMessage($message)
        );

        if ($request->has('status') && $request->status !== 'all') {
            $lessonRequests = $lessonRequests->filter(
                fn ($req) => $req['status'] === $request->status
            );
        }

        return response()->json([
            'success' => true,
            'data' => $lessonRequests->values(),
            'current_page' => $messages->currentPage(),
            'per_page' => $messages->perPage(),
            'total' => $messages->total(),
            'last_page' => $messages->lastPage(),
        ]);
    }
}
