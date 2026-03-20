<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tutors', function (Blueprint $table) {
            // Maps subject => array of year levels (e.g. {"Mathematics":["7","8"]})
            $table->json('subject_year_mapping')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tutors', function (Blueprint $table) {
            $table->dropColumn('subject_year_mapping');
        });
    }
};

