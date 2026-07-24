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
        Schema::table('packages', function (Blueprint $table) {
            $table->string('subject')->nullable()->after('description');
            $table->enum('billing_type', ['one-time', 'recurring'])->default('recurring')->after('subject');
            $table->enum('package_type', ['group', '1:1', 'hybrid'])->default('group')->after('billing_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['subject', 'billing_type', 'package_type']);
        });
    }
};
