<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\ClassModel;
use Illuminate\Http\Request;

class AdminPackageController extends Controller
{
    public function index(Request $request)
    {
        $query = Package::with('classes');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $packages = $query->orderBy('price')->get();

        return response()->json([
            'success' => true,
            'data' => $packages,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'student_limit' => 'required|integer|min:0',
            'class_ids' => 'required|array|min:1',
            'class_ids.*' => 'exists:classes,id',
            'allows_one_on_one' => 'boolean',
            'bank_details' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $classIds = $validated['class_ids'] ?? [];
        unset($validated['class_ids']);

        $package = Package::create($validated);
        
        // Attach classes
        if (!empty($classIds)) {
            $package->classes()->attach($classIds);
        }

        return response()->json([
            'success' => true,
            'data' => $package->load('classes'),
            'message' => 'Package created successfully',
        ], 201);
    }

    public function show($id)
    {
        $package = Package::with('classes')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $package,
        ]);
    }

    public function update(Request $request, $id)
    {
        $package = Package::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
            'student_limit' => 'sometimes|integer|min:0',
            'class_ids' => 'sometimes|array|min:1',
            'class_ids.*' => 'exists:classes,id',
            'allows_one_on_one' => 'sometimes|boolean',
            'bank_details' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $classIds = $validated['class_ids'] ?? null;
        unset($validated['class_ids']);

        $package->update($validated);
        
        // Sync classes if provided
        if ($classIds !== null) {
            $package->classes()->sync($classIds);
        }

        return response()->json([
            'success' => true,
            'data' => $package->fresh('classes'),
            'message' => 'Package updated successfully',
        ]);
    }

    public function destroy($id)
    {
        // Packages should not be deleted to maintain subscription history
        // Instead, deactivate them using the is_active field
        return response()->json([
            'success' => false,
            'message' => 'Packages cannot be deleted. Please deactivate them instead to maintain subscription history.',
        ], 403);
    }
}
