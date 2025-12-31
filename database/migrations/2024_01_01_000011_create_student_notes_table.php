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
        Schema::create('student_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('tutoring_sessions')->onDelete('cascade');
            $table->foreignId('student_id')->constrained()->onDelete('cascade');
            $table->text('behavior_issues')->nullable();
            $table->boolean('homework_completed')->default(false);
            $table->text('homework_notes')->nullable();
            $table->text('private_notes')->nullable();
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
        Schema::dropIfExists('student_notes');
    }
};

