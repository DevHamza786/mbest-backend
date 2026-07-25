<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminUserController extends Controller
{
    /**
     * All non-admin users, for admin to start a conversation with.
     */
    public function recipients(Request $request)
    {
        $users = User::where('role', '!=', 'admin')
            ->select('id', 'name', 'email', 'role', 'avatar')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    public function index(Request $request)
    {
        $query = User::query();

        // Exclude admin users from the list
        $query->where('role', '!=', 'admin');

        // Filter by one or more roles (comma-separated: tutor,parent)
        if ($request->filled('roles')) {
            $roles = array_values(array_filter(array_map('trim', explode(',', (string) $request->input('roles')))));
            $allowed = ['tutor', 'parent', 'student'];
            $roles = array_values(array_intersect($roles, $allowed));
            if ($roles !== []) {
                $query->whereIn('role', $roles);
            }
        } elseif ($request->filled('role')) {
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
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Tutor subjects taught (matches tutors.specialization; narrows to users who have a tutor profile)
        if ($request->filled('specialization')) {
            $specialization = $request->input('specialization');
            $query->whereHas('tutor', function ($q) use ($specialization) {
                $q->whereJsonContains('specialization', $specialization)
                    ->orWhere('specialization', 'like', '%' . $specialization . '%');
            });
        }

        $perPage = $request->get('per_page', 15);
        $users = $query->with([
            'package:id,name',
            'tutor',
            'student.parents' => function ($query) {
                $query->select('users.id', 'users.name', 'users.email', 'users.avatar', 'users.phone', 'users.package_id')
                    ->with('package:id,name');
            },
            'parentModel',
        ])->paginate($perPage);

        $collection = $users->getCollection();

        $tutorProfileIds = $collection->filter(fn ($u) => $u->role === 'tutor' && $u->tutor)->pluck('tutor.id')->unique()->values();
        $tutorStudentCounts = $tutorProfileIds->isEmpty()
            ? collect()
            : DB::table('class_student')
                ->join('classes', 'classes.id', '=', 'class_student.class_id')
                ->whereIn('classes.tutor_id', $tutorProfileIds)
                ->selectRaw('classes.tutor_id, COUNT(DISTINCT class_student.student_id) as cnt')
                ->groupBy('classes.tutor_id')
                ->pluck('cnt', 'tutor_id');

        $parentUserIds = $collection->filter(fn ($u) => $u->role === 'parent')->pluck('id')->unique()->values();
        $parentChildCounts = $parentUserIds->isEmpty()
            ? collect()
            : DB::table('parent_student')
                ->whereIn('parent_id', $parentUserIds)
                ->selectRaw('parent_id, COUNT(*) as cnt')
                ->groupBy('parent_id')
                ->pluck('cnt', 'parent_id');

        $studentRowIds = $collection->filter(fn ($u) => $u->role === 'student' && $u->student)->pluck('student.id')->unique()->values();
        $studentClassCounts = $studentRowIds->isEmpty()
            ? collect()
            : DB::table('class_student')
                ->whereIn('student_id', $studentRowIds)
                ->selectRaw('student_id, COUNT(*) as cnt')
                ->groupBy('student_id')
                ->pluck('cnt', 'student_id');

        // Convert avatar paths to full URLs and attach parent info to students
        $collection->transform(function ($user) use ($tutorStudentCounts, $parentChildCounts, $studentClassCounts) {
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

            // Incomplete badge: student/parent — base fields; tutor — tutors.profile_complete
            $baseComplete = !empty($user->phone) && !empty($user->date_of_birth) && !empty($user->address);

            $isIncompleteProfile = false;
            if (in_array($user->role, ['student', 'parent'], true)) {
                $isIncompleteProfile = !$baseComplete;
            } elseif ($user->role === 'tutor') {
                $tutor = $user->tutor;
                $isIncompleteProfile = !$tutor || !$tutor->profile_complete;
            }

            $user->setAttribute('is_incomplete_profile', $isIncompleteProfile);

            // Admin list: package + counts (role-specific meaning on the frontend)
            if ($user->role === 'tutor' && $user->tutor) {
                $user->setAttribute(
                    'tutor_students_taught_count',
                    (int) ($tutorStudentCounts[$user->tutor->id] ?? 0)
                );
            } else {
                $user->setAttribute('tutor_students_taught_count', 0);
            }

            if ($user->role === 'parent') {
                $user->setAttribute('children_count', (int) ($parentChildCounts[$user->id] ?? 0));
            } else {
                $user->setAttribute('children_count', 0);
            }

            if ($user->role === 'student' && $user->student) {
                $user->setAttribute(
                    'enrolled_classes_count',
                    (int) ($studentClassCounts[$user->student->id] ?? 0)
                );
                $familyPackageName = null;
                if ($user->student->relationLoaded('parents')) {
                    foreach ($user->student->parents as $p) {
                        if ($p->relationLoaded('package') && $p->package) {
                            $familyPackageName = $p->package->name;
                            break;
                        }
                    }
                }
                $user->setAttribute('family_package_name', $familyPackageName);
            } else {
                $user->setAttribute('enrolled_classes_count', 0);
                $user->setAttribute('family_package_name', null);
            }

            return $user;
        });

        $users->setCollection($collection);

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
            'parent_id' => 'nullable|exists:users,id',
            'grade' => 'nullable|string|max:50',
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

        // Ensure student role always has a linked Student profile with an
        // auto-generated enrollment ID (previously this endpoint created a
        // bare User with no Student row at all).
        if ($validated['role'] === 'student') {
            \App\Models\Student::create([
                'user_id' => $user->id,
                'parent_id' => $validated['parent_id'] ?? null,
                'enrollment_id' => app(\App\Services\StudentService::class)->generateEnrollmentId(),
                'grade' => $validated['grade'] ?? null,
            ]);
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

        try {
            $user->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            // Foreign key constraint (e.g. invoices/payments referencing this
            // user) - can't hard-delete without losing billing history.
            // Deactivate instead, same as packages/classes do.
            $user->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'This user has related billing records and cannot be permanently deleted. Their account has been deactivated instead.',
                'data' => ['deactivated' => true],
            ]);
        }

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

