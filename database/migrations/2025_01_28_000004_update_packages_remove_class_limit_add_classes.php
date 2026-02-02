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
        // Remove class_limit column from packages table
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn('class_limit');
        });

        // Create package_class pivot table
        Schema::create('package_class', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->onDelete('cascade');
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['package_id', 'class_id']);
            $table->index('package_id');
            $table->index('class_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('package_class');
        
        Schema::table('packages', function (Blueprint $table) {
            $table->integer('class_limit')->default(0)->after('course_limit');
        });
    }
};
