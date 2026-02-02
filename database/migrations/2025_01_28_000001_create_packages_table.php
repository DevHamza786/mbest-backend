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
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Package Name
            $table->decimal('price', 10, 2); // Package Price
            $table->text('description')->nullable(); // Description
            $table->integer('student_limit')->default(0); // Number of Students (Limit)
            $table->integer('course_limit')->default(0); // Number of Courses (Limit)
            $table->integer('class_limit')->default(0); // Number of Classes (Limit)
            $table->boolean('allows_one_on_one')->default(false); // 1:1 Session Request (Boolean)
            $table->text('bank_details')->nullable(); // Bank Details for payment instructions
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('packages');
    }
};
