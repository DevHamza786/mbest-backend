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
        // Add 'unavailable' to the status enum
        DB::statement("ALTER TABLE tutoring_sessions MODIFY COLUMN status ENUM('planned', 'completed', 'cancelled', 'no-show', 'rescheduled', 'unavailable') DEFAULT 'planned'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'unavailable' from the status enum
        DB::statement("ALTER TABLE tutoring_sessions MODIFY COLUMN status ENUM('planned', 'completed', 'cancelled', 'no-show', 'rescheduled') DEFAULT 'planned'");
    }
};
