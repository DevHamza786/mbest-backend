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
        // Table already created in 2025_12_26_122750_create_questions_table migration
        // This migration is redundant - table already exists with proper structure
        // Skip creation if table already exists to avoid duplicate table error
        if (!Schema::hasTable('question_attachments')) {
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('question_attachments');
    }
};
