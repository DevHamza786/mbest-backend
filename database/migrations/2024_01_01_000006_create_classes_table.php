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
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('tutor_id')->constrained()->onDelete('restrict');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->enum('level', ['Beginner', 'Intermediate', 'Advanced'])->nullable();
            $table->integer('capacity')->default(30);
            $table->integer('enrolled')->default(0);
            $table->integer('credits')->nullable();
            $table->string('duration')->nullable();
            $table->enum('status', ['active', 'inactive', 'completed'])->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
            
            $table->index('tutor_id');
            $table->index('code');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};

