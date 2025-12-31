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
        Schema::create('tutoring_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->foreignId('teacher_id')->constrained('tutors')->onDelete('restrict');
            $table->string('subject');
            $table->string('year_level', 50)->nullable();
            $table->enum('location', ['online', 'centre', 'home']);
            $table->enum('session_type', ['1:1', 'group']);
            $table->enum('status', ['planned', 'completed', 'cancelled', 'no-show', 'rescheduled'])->default('planned');
            $table->text('lesson_note')->nullable();
            $table->text('topics_taught')->nullable();
            $table->text('homework_resources')->nullable();
            $table->boolean('attendance_marked')->default(false);
            $table->boolean('ready_for_invoicing')->default(false);
            $table->string('color', 7)->nullable();
            $table->timestamps();
            
            $table->index('teacher_id');
            $table->index('date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tutoring_sessions');
    }
};

