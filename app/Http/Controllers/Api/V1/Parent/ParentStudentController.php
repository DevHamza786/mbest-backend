<?php

namespace App\Http\Controllers\Api\V1\Parent;

use App\Http\Controllers\Controller;
use App\Services\StudentService;
use Illuminate\Http\Request;

class ParentStudentController extends Controller
{
    protected $studentService;

    public function __construct(StudentService $studentService)
    {
        $this->studentService = $studentService;
    }

    public function store(Request $request)
    {
        $user = $request->user();

        // Treat empty strings as null so `nullable` validations work as intended.
        $request->merge([
            'phone' => $request->input('phone') === '' ? null : $request->input('phone'),
            'date_of_birth' => $request->input('date_of_birth') === '' ? null : $request->input('date_of_birth'),
            'emergency_contact_phone' => $request->input('emergency_contact_phone') === '' ? null : $request->input('emergency_contact_phone'),
            'grade' => $request->input('grade') === '' ? null : $request->input('grade'),
        ]);

        $validated = $request->validate([
            "name" => "required|string|max:255|regex:/^[A-Za-z][A-Za-z\\s.'-]*$/",
            'email' => 'required|string|email:rfc,dns|max:255|unique:users,email',
            'password' => [
                'required',
                'string',
                'min:8',
                // Require: upper/lowercase, number, and special character.
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
            ],
            'grade' => [
                'nullable',
                'string',
                'max:50',
                // Year level format like "Year 10".
                'regex:/^Year (?:[1-9]|1[0-2])$/',
            ],
            'school' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date|before_or_equal:today',
            'address' => 'nullable|string',
            "emergency_contact_name" => "nullable|string|max:255|regex:/^[A-Za-z][A-Za-z\\s.'-]*$/",
            'emergency_contact_phone' => 'nullable|string|max:20',
        ]);

        // Normalize phone inputs (strip spaces/dashes) before storing.
        if (isset($validated['phone']) && $validated['phone'] !== null) {
            $validated['phone'] = preg_replace('/[\s-]/', '', (string) $validated['phone']);
        }
        if (isset($validated['emergency_contact_phone']) && $validated['emergency_contact_phone'] !== null) {
            $validated['emergency_contact_phone'] = preg_replace('/[\s-]/', '', (string) $validated['emergency_contact_phone']);
        }

        try {
            $student = $this->studentService->createStudentForParent($user, $validated);

            return response()->json([
                'success' => true,
                'data' => $student,
                'message' => 'Student added successfully',
            ], 201);
        } catch (\Exception $e) {
            $limitReached = $e->getMessage() === 'Student limit reached for your subscription package';

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'limit_reached' => $limitReached,
            ], 400);
        }
    }
}
