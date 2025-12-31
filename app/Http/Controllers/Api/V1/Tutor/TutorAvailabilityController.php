<?php

namespace App\Http\Controllers\Api\V1\Tutor;

use App\Http\Controllers\Controller;
use App\Models\Tutor;
use App\Models\TutorAvailability;
use Illuminate\Http\Request;

class TutorAvailabilityController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $availability = TutorAvailability::where('tutor_id', $tutor->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $availability,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $validated = $request->validate([
            'availability' => 'required|array|min:1',
            'availability.*.day_of_week' => 'required|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'availability.*.start_time' => 'required|date_format:H:i',
            'availability.*.end_time' => 'required|date_format:H:i|after:availability.*.start_time',
            'availability.*.is_available' => 'nullable|boolean',
        ]);

        // Delete existing availability
        TutorAvailability::where('tutor_id', $tutor->id)->delete();

        // Create new availability
        foreach ($validated['availability'] as $slot) {
            TutorAvailability::create([
                'tutor_id' => $tutor->id,
                'day_of_week' => $slot['day_of_week'],
                'start_time' => $slot['start_time'],
                'end_time' => $slot['end_time'],
                'is_available' => $slot['is_available'] ?? true,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => TutorAvailability::where('tutor_id', $tutor->id)->get(),
            'message' => 'Availability set successfully',
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $availability = TutorAvailability::where('tutor_id', $tutor->id)->findOrFail($id);

        $validated = $request->validate([
            'day_of_week' => 'sometimes|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
            'start_time' => 'sometimes|date_format:H:i',
            'end_time' => 'sometimes|date_format:H:i|after:start_time',
            'is_available' => 'sometimes|boolean',
        ]);

        $availability->update($validated);

        return response()->json([
            'success' => true,
            'data' => $availability,
            'message' => 'Availability updated successfully',
        ]);
    }

    public function destroy($id)
    {
        $user = $request->user();
        $tutor = Tutor::where('user_id', $user->id)->firstOrFail();

        $availability = TutorAvailability::where('tutor_id', $tutor->id)->findOrFail($id);
        $availability->delete();

        return response()->json([
            'success' => true,
            'message' => 'Availability deleted successfully',
        ]);
    }
}

