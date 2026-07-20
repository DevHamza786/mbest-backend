<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('subscription_status', ['pending', 'active', 'expired', 'cancelled'])->nullable()->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->whereNull('subscription_status')->update(['subscription_status' => 'pending']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('subscription_status', ['pending', 'active', 'expired', 'cancelled'])->default('pending')->change();
        });
    }
};
