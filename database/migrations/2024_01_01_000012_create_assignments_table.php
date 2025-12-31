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
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->text('instructions')->nullable();
            $table->foreignId('class_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('tutor_id')->constrained()->onDelete('restrict');
            $table->dateTime('due_date');
            $table->integer('max_points');
            $table->enum('submission_type', ['file', 'text', 'link'])->default('file');
            $table->json('allowed_file_types')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamps();
            
            $table->index('class_id');
            $table->index('tutor_id');
            $table->index('status');
            $table->index('due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};

