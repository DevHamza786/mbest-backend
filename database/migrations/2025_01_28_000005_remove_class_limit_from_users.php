<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Only drop the column if it exists
            if (Schema::hasColumn('users', 'current_class_count')) {
                $table->dropColumn('current_class_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Only add the column if it doesn't exist
            if (!Schema::hasColumn('users', 'current_class_count')) {
                $table->integer('current_class_count')->default(0)->after('current_course_count');
            }
        });
    }
};
