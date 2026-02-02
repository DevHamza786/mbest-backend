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
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('package_id')->nullable()->after('is_active')->constrained('packages')->onDelete('set null');
            $table->enum('subscription_status', ['pending', 'active', 'expired', 'cancelled'])->default('pending')->after('package_id');
            $table->timestamp('subscription_approved_at')->nullable()->after('subscription_status');
            $table->integer('current_student_count')->default(0)->after('subscription_approved_at');
            $table->integer('current_course_count')->default(0)->after('current_student_count');
            
            $table->index('subscription_status');
            $table->index('package_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropIndex(['subscription_status']);
            $table->dropIndex(['package_id']);
            $table->dropColumn([
                'package_id',
                'subscription_status',
                'subscription_approved_at',
                'current_student_count',
                'current_course_count',
            ]);
        });
    }
};
