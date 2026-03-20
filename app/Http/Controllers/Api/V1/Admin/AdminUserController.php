<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Tutor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminUserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        // Exclude admin users from the list
        $query->where('role', '!=', 'admin');

        // Filter by role
        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        $perPage = $request->get('per_page', 15);
        $users = $query->with([
            'tutor', 
            'student.parents' => function ($query) {
                $query->select('users.id', 'users.name', 'users.email', 'users.avatar', 'users.phone');
            },
            'parentModel'
        ])->paginate($perPage);

        // Convert avatar paths to full URLs and attach parent info to students
        $users->getCollection()->transform(function ($user) {
            // Convert avatar to full URL if it exists
            if ($user->avatar && !filter_var($user->avatar, FILTER_VALIDATE_URL)) {
                $user->avatar = Storage::url($user->avatar);
            }
            
            // If user is a student, attach parent information (already loaded via eager loading)
            if ($user->role === 'student' && $user->student && $user->student->relationLoaded('parents')) {
                $parents = $user->student->parents->map(function ($parent) {
                    if ($parent->avatar && !filter_var($parent->avatar, FILTER_VALIDATE_URL)) {
                        $parent->avatar = Storage::url($parent->avatar);
                    }
                    return [
                        'id' => $parent->id,
                        'name' => $parent->name,
                        'email' => $parent->email,
                        'avatar' => $parent->avatar,
                        'phone' => $parent->phone,
                    ];
                });
                $user->setAttribute('parents_data', $parents->values());
            }
            
            return $user;
        });

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function store(Request $request)
    {
        // Treat empty strings as null so `nullable` validations work as intended.
        $request->merge([
            'phone' => $request->input('phone') === '' ? null : $request->input('phone'),
            'date_of_birth' => $request->input('date_of_birth') === '' ? null : $request->input('date_of_birth'),
        ]);

        $validated = $request->validate([
            "name" => "required|string|max:255|regex:/^[A-Za-z][A-Za-z\\s.'-]*$/",
            'email' => 'required|string|email:rfc,dns|max:255|unique:users',
            // Require strong password: upper/lowercase, number, and special character.
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
            ],
            'role' => 'required|in:admin,tutor,student,parent',
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
        ]);

        if (isset($validated['phone']) && $validated['phone'] !== null) {
            $validated['phone'] = preg_replace('/[\s-]/', '', (string) $validated['phone']);
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => $validated['role'],
            'phone' => $validated['phone'] ?? null,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'address' => $validated['address'] ?? null,
        ]);

        // Ensure tutor role always has a tutor profile row.
        if ($validated['role'] === 'tutor') {
            Tutor::firstOrCreate(
                ['user_id' => $user->id],
                ['is_available' => true]
            );
        }

        return response()->json([
            'success' => true,
            'data' => $user,
            'message' => 'User created successfully',
        ], 201);
    }

    public function show($id)
    {
        $user = User::with(['tutor', 'student', 'parent'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user,
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        // Treat empty strings as null so `nullable` validations work as intended.
        $request->merge([
            'phone' => $request->input('phone') === '' ? null : $request->input('phone'),
            'date_of_birth' => $request->input('date_of_birth') === '' ? null : $request->input('date_of_birth'),
        ]);

        $validated = $request->validate([
            "name" => "sometimes|string|max:255|regex:/^[A-Za-z][A-Za-z\\s.'-]*$/",
            'email' => 'sometimes|string|email:rfc,dns|max:255|unique:users,email,' . $id,
            'password' => [
                'sometimes',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
            ],
            'role' => 'sometimes|in:admin,tutor,student,parent',
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
            'is_active' => 'sometimes|boolean',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }

        if (isset($validated['phone']) && $validated['phone'] !== null) {
            $validated['phone'] = preg_replace('/[\s-]/', '', (string) $validated['phone']);
        }

        $user->update($validated);

        // If the user is (now) a tutor, ensure the tutor profile row exists.
        if (($validated['role'] ?? $user->role) === 'tutor') {
            Tutor::firstOrCreate(
                ['user_id' => $user->id],
                ['is_available' => true]
            );
        }

        return response()->json([
            'success' => true,
            'data' => $user,
            'message' => 'User updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }

    public function stats()
    {
        $stats = [
            'total' => User::where('role', '!=', 'admin')->count(),
            'students' => User::where('role', 'student')->count(),
            'tutors' => User::where('role', 'tutor')->count(),
            'parents' => User::where('role', 'parent')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get tutor by id (for invoice: hourly_rate)
     */
    public function getTutor($id)
    {
        $tutor = Tutor::with('user:id,name,email')->findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => [
                'id' => $tutor->id,
                'user_id' => $tutor->user_id,
                'hourly_rate' => $tutor->hourly_rate,
                'user' => $tutor->user,
            ],
        ]);
    }
}

