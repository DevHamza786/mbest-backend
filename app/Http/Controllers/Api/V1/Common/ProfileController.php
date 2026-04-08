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

        // Treat empty strings as null so `nullable` validations work as intended.
        $request->merge([
            'phone' => $request->input('phone') === '' ? null : $request->input('phone'),
            'date_of_birth' => $request->input('date_of_birth') === '' ? null : $request->input('date_of_birth'),
            'wwcc_number' => $request->input('wwcc_number') === '' ? null : $request->input('wwcc_number'),
            'wwcc_expiry_date' => $request->input('wwcc_expiry_date') === '' ? null : $request->input('wwcc_expiry_date'),
            'max_students_per_group' => $request->input('max_students_per_group') === '' ? null : $request->input('max_students_per_group'),
        ]);

        $validated = $request->validate([
            "name" => "sometimes|string|max:255|regex:/^[A-Za-z][A-Za-z\\s.'-]*$/",
            'email' => 'sometimes|string|email:rfc,dns|max:255|unique:users,email,' . $user->id,
            'phone' => [
                'nullable',
                'string',
                'max:20',
                function ($attribute, $value, $fail) {
                    if ($value === null || $value === '') return;
                    $normalized = preg_replace('/[\s-]/', '', (string) $value);
                    if (!preg_match('/^\+61\d{9}$/', $normalized)) {
                        $fail('The ' . $attribute . ' must be a valid Australian number in the format +61XXXXXXXXX.');
                    }
                },
            ],
            'date_of_birth' => 'nullable|date|before_or_equal:today',
            'address' => 'nullable|string',
            'avatar' => 'nullable|image|max:2048',
            'password' => [
                'sometimes',
                'string',
                'min:8',
                'confirmed',
                // Require: upper/lowercase, number, and special character.
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
            ],
            // Tutor-specific fields
            'specialization' => 'nullable|array',
            'specialization.*' => 'string|max:255',
            'subject_year_mapping' => 'nullable|array',
            'subject_year_mapping.*' => 'array',
            // Avoid regex here (it can trigger preg_match delimiter errors for some inputs).
            // Years come from UI as numeric strings like "5", so validate as integer range 1..12.
            'subject_year_mapping.*.*' => 'integer|between:1,12',
            'hourly_rate' => 'nullable|numeric|min:0',
            'qualifications' => 'nullable|string',
            'experience_years' => 'nullable|integer|min:0',
            'bio' => 'nullable|string',

            // WWCC + group size limits (tutor onboarding)
            'wwcc_number' => 'nullable|string|max:100',
            'wwcc_expiry_date' => 'nullable|date|after_or_equal:today',
            'max_students_per_group' => 'nullable|integer|min:1|max:100',
        ]);

        // Normalize phone inputs (strip spaces/dashes) before storing.
        if (isset($validated['phone']) && $validated['phone'] !== null) {
            $validated['phone'] = preg_replace('/[\s-]/', '', (string) $validated['phone']);
        }

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
        $tutorFields = [
            'specialization',
            'subject_year_mapping',
            'hourly_rate',
            'qualifications',
            'experience_years',
            'bio',
            'wwcc_number',
            'wwcc_expiry_date',
            'max_students_per_group',
        ];
        
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
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                // Require: upper/lowercase, number, and special character.
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
            ],
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

