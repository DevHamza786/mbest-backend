<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing sessions with class_id based on their students
        // For each session, find the class that has the most students from that session
        $sessions = DB::table('tutoring_sessions')
            ->whereNull('class_id')
            ->get();

        foreach ($sessions as $session) {
            // Get students for this session
            $sessionStudents = DB::table('session_student')
                ->where('session_id', $session->id)
                ->pluck('student_id')
                ->toArray();

            if (empty($sessionStudents)) {
                continue;
            }

            // Find the class that has the most matching students
            $classMatch = DB::table('class_student')
                ->whereIn('student_id', $sessionStudents)
                ->select('class_id', DB::raw('COUNT(*) as match_count'))
                ->groupBy('class_id')
                ->orderBy('match_count', 'desc')
                ->first();

            if ($classMatch) {
                // Update session with the most matching class
                DB::table('tutoring_sessions')
                    ->where('id', $session->id)
                    ->update(['class_id' => $classMatch->class_id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Optionally clear class_id if needed
        // DB::table('tutoring_sessions')->update(['class_id' => null]);
    }
};

