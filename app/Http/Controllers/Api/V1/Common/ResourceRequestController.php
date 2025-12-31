<?php

namespace App\Http\Controllers\Api\V1\Common;

use App\Http\Controllers\Controller;
use App\Models\ResourceRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ResourceRequestController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Get resource requests
     * Students can see their own requests
     * Tutors and admins can see all requests
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = ResourceRequest::with(['requestedBy', 'reviewedBy']);

        // Students can only see their own requests
        if ($user->role === 'student') {
            $query->where('requested_by', $user->id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        $perPage = $request->get('per_page', 15);
        $requests = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Return paginated response in Laravel format
        return response()->json($requests);
    }

    /**
     * Create a new resource request (students only)
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // Only students can create resource requests
        if ($user->role !== 'student') {
            return response()->json([
                'success' => false,
                'message' => 'Only students can request resources',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|string|max:100',
            'type' => 'required|string|max:100',
            'priority' => 'nullable|in:low,medium,high,urgent',
        ]);

        $resourceRequest = ResourceRequest::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'category' => $validated['category'],
            'type' => $validated['type'],
            'priority' => $validated['priority'] ?? 'medium',
            'status' => 'pending',
            'requested_by' => $user->id,
        ]);

        // Notify all tutors about the new resource request
        $tutors = User::where('role', 'tutor')->get();
        $tutorIds = $tutors->pluck('id')->toArray();

        if (!empty($tutorIds)) {
            $this->notificationService->notifyUsers(
                $tutorIds,
                'resource_request',
                'New Resource Request',
                "Student {$user->name} has requested a new resource: {$resourceRequest->title}",
                [
                    'resource_request_id' => $resourceRequest->id,
                    'student_id' => $user->id,
                    'student_name' => $user->name,
                ],
                'medium'
            );
        }

        return response()->json([
            'success' => true,
            'data' => $resourceRequest->load('requestedBy'),
            'message' => 'Resource request submitted successfully',
        ], 201);
    }

    /**
     * Get a specific resource request
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $resourceRequest = ResourceRequest::with(['requestedBy', 'reviewedBy'])->findOrFail($id);

        // Students can only see their own requests
        if ($user->role === 'student' && $resourceRequest->requested_by !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $resourceRequest->load(['requestedBy', 'reviewedBy']),
        ]);
    }

    /**
     * Update resource request status (tutors and admins only)
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        // Only tutors and admins can update resource requests
        if (!in_array($user->role, ['tutor', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $resourceRequest = ResourceRequest::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:pending,approved,rejected,fulfilled',
            'review_notes' => 'nullable|string',
        ]);

        $resourceRequest->update([
            'status' => $validated['status'],
            'reviewed_by' => $user->id,
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_at' => now(),
        ]);

        // Notify the student about the status update
        $statusMessages = [
            'approved' => 'Your resource request has been approved',
            'rejected' => 'Your resource request has been rejected',
            'fulfilled' => 'Your resource request has been fulfilled',
        ];

        $message = $statusMessages[$validated['status']] ?? 'Your resource request status has been updated';
        
        $this->notificationService->createNotification(
            $resourceRequest->requested_by,
            'resource_request',
            'Resource Request Update',
            "{$message}: {$resourceRequest->title}",
            [
                'resource_request_id' => $resourceRequest->id,
                'status' => $validated['status'],
            ],
            $validated['status'] === 'approved' ? 'high' : 'medium'
        );

        return response()->json([
            'success' => true,
            'data' => $resourceRequest->load(['requestedBy', 'reviewedBy']),
            'message' => 'Resource request updated successfully',
        ]);
    }

    /**
     * Delete a resource request
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $resourceRequest = ResourceRequest::findOrFail($id);

        // Students can only delete their own pending requests
        // Tutors and admins can delete any request
        if ($user->role === 'student') {
            if ($resourceRequest->requested_by !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }
            if ($resourceRequest->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete a request that has been reviewed',
                ], 403);
            }
        }

        $resourceRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Resource request deleted successfully',
        ]);
    }
}

