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
        Schema::create('session_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('tutoring_sessions')->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->enum('attendance_status', ['present', 'absent', 'late', 'excused'])->nullable();
            $table->timestamps();
            
            $table->unique(['session_id', 'student_id']);
            $table->index('session_id');
            $table->index('student_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('session_student');
    }
};

