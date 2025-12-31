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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('tutor_id')->nullable()->constrained('tutors')->onDelete('set null');
            $table->foreignId('assignment_id')->nullable()->constrained('assignments')->onDelete('set null');
            $table->foreignId('class_id')->nullable()->constrained('classes')->onDelete('set null');
            $table->string('subject');
            $table->text('question');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->enum('category', ['assignment', 'concept', 'technical', 'grading', 'general'])->default('general');
            $table->enum('status', ['pending', 'answered', 'closed'])->default('pending');
            $table->text('answer')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('question_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('questions')->onDelete('cascade');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type');
            $table->unsignedBigInteger('file_size');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_attachments');
        Schema::dropIfExists('questions');
    }
};
