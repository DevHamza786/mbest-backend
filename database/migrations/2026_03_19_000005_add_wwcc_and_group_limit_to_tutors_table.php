<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tutors', function (Blueprint $table) {
            // WWCC details
            $table->string('wwcc_number', 100)->nullable()->after('hourly_rate');
            $table->date('wwcc_expiry_date')->nullable()->after('wwcc_number');

            // Maximum students per group session taught by this tutor
            $table->integer('max_students_per_group')->nullable()->after('wwcc_expiry_date');
        });
    }

    public function down(): void
    {
        Schema::table('tutors', function (Blueprint $table) {
            $table->dropColumn('max_students_per_group');
            $table->dropColumn('wwcc_expiry_date');
            $table->dropColumn('wwcc_number');
        });
    }
};

