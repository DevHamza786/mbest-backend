<?php

namespace App\Http\Controllers\Api\V1\Common;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ResourceController extends Controller
{
    public function index(Request $request)
    {
        $query = Resource::query();

        // Filter by class
        if ($request->has('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
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
        $resources = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $resources,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // Only tutors and admins can upload resources
        if (!in_array($user->role, ['tutor', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        // Normalize is_public value from form data (string "true"/"false" to boolean)
        if ($request->has('is_public')) {
            $request->merge([
                'is_public' => filter_var($request->input('is_public'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false
            ]);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'class_id' => 'nullable|exists:classes,id',
            'type' => 'required|in:document,link,pdf,video',
            'category' => 'nullable|string|max:100',
            'tags' => 'nullable|string',
            'url' => 'required_if:type,link|url',
            'file' => 'required_if:type,document,pdf,video|file|max:102400',
            'is_public' => 'nullable|boolean',
        ]);

        $resourceData = [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'class_id' => $validated['class_id'] ?? null,
            'type' => $validated['type'],
            'category' => $validated['category'] ?? null,
            'uploaded_by' => $user->id,
            'is_public' => $request->boolean('is_public', false),
        ];

        // Handle tags - convert comma-separated string to array
        if (!empty($validated['tags'])) {
            $tags = array_map('trim', explode(',', $validated['tags']));
            $tags = array_filter($tags); // Remove empty values
            $resourceData['tags'] = !empty($tags) ? $tags : null;
        }

        // Handle file upload
        if (in_array($validated['type'], ['document', 'pdf', 'video']) && $request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('resources', 'public');
            $resourceData['url'] = Storage::url($path);
            $resourceData['file_path'] = $path;
            $resourceData['file_size'] = $file->getSize();
        } else {
            $resourceData['url'] = $validated['url'];
        }

        $resource = Resource::create($resourceData);

        return response()->json([
            'success' => true,
            'data' => $resource,
            'message' => 'Resource uploaded successfully',
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $resource = Resource::findOrFail($id);

        // Increment download count
        $resource->increment('downloads');

        return response()->json([
            'success' => true,
            'data' => $resource,
        ]);
    }

    public function download(Request $request, $id)
    {
        $resource = Resource::findOrFail($id);

        if (!$resource->file_path || !Storage::disk('public')->exists($resource->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'File not found',
            ], 404);
        }

        // Increment download count
        $resource->increment('downloads');

        return Storage::disk('public')->download(
            $resource->file_path,
            $resource->file_name ?? basename($resource->file_path)
        );
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();

        // Only tutors and admins can update resources
        if (!in_array($user->role, ['tutor', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $resource = Resource::findOrFail($id);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'nullable|string|max:100',
        ]);

        $resource->update($validated);

        return response()->json([
            'success' => true,
            'data' => $resource,
            'message' => 'Resource updated successfully',
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        // Only tutors and admins can delete resources
        if (!in_array($user->role, ['tutor', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $resource = Resource::findOrFail($id);

        // Delete file if exists
        if ($resource->file_path && Storage::disk('public')->exists($resource->file_path)) {
            Storage::disk('public')->delete($resource->file_path);
        }

        $resource->delete();

        return response()->json([
            'success' => true,
            'message' => 'Resource deleted successfully',
        ]);
    }
}

