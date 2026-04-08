<?php

use App\Models\Tutor;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tutors', function (Blueprint $table) {
            $table->boolean('profile_complete')->default(false)->after('user_id');
        });

        Tutor::with('user')->chunkById(100, function ($tutors) {
            foreach ($tutors as $tutor) {
                $tutor->syncProfileCompleteFlag();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tutors', function (Blueprint $table) {
            $table->dropColumn('profile_complete');
        });
    }
};
