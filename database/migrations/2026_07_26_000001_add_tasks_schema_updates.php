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
        if (!Schema::hasColumn('invoices', 'receipt_file')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('receipt_file', 500)->nullable()->after('transaction_id');
            });
        }

        if (!Schema::hasColumn('resource_requests', 'fulfilled_file')) {
            Schema::table('resource_requests', function (Blueprint $table) {
                $table->string('fulfilled_file', 500)->nullable()->after('review_notes');
            });
        }

        if (!Schema::hasTable('resource_user')) {
            Schema::create('resource_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('resource_id')->constrained()->onDelete('cascade');
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->timestamps();

                $table->unique(['resource_id', 'user_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('invoices', 'receipt_file')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('receipt_file');
            });
        }

        if (Schema::hasColumn('resource_requests', 'fulfilled_file')) {
            Schema::table('resource_requests', function (Blueprint $table) {
                $table->dropColumn('fulfilled_file');
            });
        }

        Schema::dropIfExists('resource_user');
    }
};
