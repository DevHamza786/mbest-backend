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
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('type', ['document', 'link', 'pdf', 'video']);
            $table->string('category', 100)->nullable();
            $table->json('tags')->nullable();
            $table->string('url', 500);
            $table->string('file_path', 500)->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('restrict');
            $table->foreignId('class_id')->nullable()->constrained()->onDelete('set null');
            $table->boolean('is_public')->default(false);
            $table->integer('downloads')->default(0);
            $table->timestamps();
            
            $table->index('uploaded_by');
            $table->index('class_id');
            $table->index('type');
            $table->index('is_public');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};

