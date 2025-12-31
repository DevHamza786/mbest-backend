<?php

namespace App\Http\Controllers\Api\V1\Common;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user();

        // Load role-specific relationships
        $user->load(['tutor', 'student', 'parentModel']);

        // If user is a student, load their parent(s) through the parent_student pivot table
        if ($user->role === 'student' && $user->student) {
            // Get the first parent through the parent_student pivot table
            $parent = $user->student->parents()->first();
            if ($parent) {
                // Load the parent's parentModel relationship
                $parent->load('parentModel');
                
                // Convert parent's avatar path to full URL if exists
                if ($parent->avatar) {
                    $parent->avatar = Storage::url($parent->avatar);
                }
                
                // Attach parent data to user object as parent_model
                $user->setAttribute('parent_model', $parent);
            } else {
                $user->setAttribute('parent_model', null);
            }
        }

        // Convert avatar path to full URL
        if ($user->avatar) {
            $user->avatar = Storage::url($user->avatar);
        }

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string',
            'avatar' => 'nullable|image|max:2048',
            'password' => 'sometimes|string|min:8|confirmed',
            // Tutor-specific fields
            'department' => 'nullable|string|max:255',
            'specialization' => 'nullable|array',
            'specialization.*' => 'string|max:255',
            'hourly_rate' => 'nullable|numeric|min:0',
            'qualifications' => 'nullable|string',
            'experience_years' => 'nullable|integer|min:0',
            'bio' => 'nullable|string',
        ]);

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
                Storage::disk('public')->delete($user->avatar);
            }

            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar'] = $path;
        }

        // Handle password update
        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }

        // Separate user fields from tutor fields
        $userFields = ['name', 'email', 'phone', 'date_of_birth', 'address', 'avatar', 'password'];
        $tutorFields = ['department', 'specialization', 'hourly_rate', 'qualifications', 'experience_years', 'bio'];
        
        $userData = array_intersect_key($validated, array_flip($userFields));
        $tutorData = array_intersect_key($validated, array_flip($tutorFields));

        // Update user fields
        if (!empty($userData)) {
            $user->update($userData);
        }

        // Update tutor-specific fields if user is a tutor
        if ($user->role === 'tutor' && $user->tutor && !empty($tutorData)) {
            // Handle specialization array
            if (isset($tutorData['specialization']) && is_string($tutorData['specialization'])) {
                // If specialization is sent as comma-separated string, convert to array
                $tutorData['specialization'] = array_map('trim', explode(',', $tutorData['specialization']));
            }
            $user->tutor->update($tutorData);
        }

        $updatedUser = $user->fresh(['tutor', 'student', 'parentModel']);
        
        // If user is a student, load their parent(s) through the parent_student pivot table
        if ($updatedUser->role === 'student' && $updatedUser->student) {
            // Get the first parent through the parent_student pivot table
            $parent = $updatedUser->student->parents()->first();
            if ($parent) {
                // Load the parent's parentModel relationship
                $parent->load('parentModel');
                
                // Convert parent's avatar path to full URL if exists
                if ($parent->avatar) {
                    $parent->avatar = Storage::url($parent->avatar);
                }
                
                // Attach parent data to user object as parent_model
                $updatedUser->setAttribute('parent_model', $parent);
            } else {
                $updatedUser->setAttribute('parent_model', null);
            }
        }
        
        // Convert avatar path to full URL
        if ($updatedUser->avatar) {
            $updatedUser->avatar = Storage::url($updatedUser->avatar);
        }

        return response()->json([
            'success' => true,
            'data' => $updatedUser,
            'message' => 'Profile updated successfully',
        ]);
    }

    public function uploadAvatar(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'avatar' => 'required|image|max:2048',
        ]);

        // Delete old avatar if exists
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }

        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar' => $path]);

        return response()->json([
            'success' => true,
            'data' => [
                'avatar' => Storage::url($path),
            ],
            'message' => 'Avatar uploaded successfully',
        ]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Verify current password
        if (!\Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 400);
        }

        $user->update([
            'password' => bcrypt($validated['password']),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }
}

