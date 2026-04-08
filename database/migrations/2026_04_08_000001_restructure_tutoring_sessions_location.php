<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace legacy `location` (enum online|centre|home or free-text) with
     * `location_type` (online|onsite) + `location_detail` (URL, room, Maps link, etc.).
     */
    public function up(): void
    {
        if (! Schema::hasTable('tutoring_sessions')) {
            return;
        }

        if (! Schema::hasColumn('tutoring_sessions', 'location')) {
            if (! Schema::hasColumn('tutoring_sessions', 'location_type')) {
                Schema::table('tutoring_sessions', function (Blueprint $table) {
                    $table->string('location_type', 20)->default('onsite')->after('year_level');
                    $table->text('location_detail')->nullable()->after('location_type');
                });
            }

            return;
        }

        Schema::table('tutoring_sessions', function (Blueprint $table) {
            $table->string('location_type', 20)->nullable()->after('year_level');
            $table->text('location_detail')->nullable()->after('location_type');
        });

        $rows = DB::table('tutoring_sessions')->select('id', 'location')->get();

        foreach ($rows as $row) {
            $raw = $row->location;
            $type = 'onsite';
            $detail = null;

            if ($raw === null || $raw === '') {
                $type = 'onsite';
            } elseif (is_string($raw)) {
                $lower = strtolower(trim($raw));
                if ($lower === 'online') {
                    $type = 'online';
                    $detail = null;
                } elseif (in_array($lower, ['centre', 'center', 'home', 'onsite'], true)) {
                    $type = 'onsite';
                    $detail = null;
                } elseif (preg_match('/^online:\s*(.*)$/i', $raw, $m)) {
                    $type = 'online';
                    $detail = trim($m[1]);
                    $detail = $detail === '' ? null : $detail;
                } elseif (preg_match('/^onsite:\s*(.*)$/i', $raw, $m)) {
                    $type = 'onsite';
                    $detail = trim($m[1]);
                    $detail = $detail === '' ? null : $detail;
                } else {
                    $type = 'onsite';
                    $detail = $raw;
                }
            }

            DB::table('tutoring_sessions')->where('id', $row->id)->update([
                'location_type' => $type,
                'location_detail' => $detail,
            ]);
        }

        Schema::table('tutoring_sessions', function (Blueprint $table) {
            $table->dropColumn('location');
        });

        DB::table('tutoring_sessions')->whereNull('location_type')->update(['location_type' => 'onsite']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tutoring_sessions')) {
            return;
        }

        if (Schema::hasColumn('tutoring_sessions', 'location')) {
            return;
        }

        Schema::table('tutoring_sessions', function (Blueprint $table) {
            $table->string('location', 255)->nullable()->after('year_level');
        });

        $rows = DB::table('tutoring_sessions')->select('id', 'location_type', 'location_detail')->get();

        foreach ($rows as $row) {
            $legacy = 'centre';
            $type = $row->location_type ?? 'onsite';
            $detail = $row->location_detail;

            if ($type === 'online') {
                $legacy = $detail ? 'Online: '.$detail : 'online';
            } else {
                $legacy = $detail ? 'Onsite: '.$detail : 'centre';
            }

            DB::table('tutoring_sessions')->where('id', $row->id)->update(['location' => $legacy]);
        }

        Schema::table('tutoring_sessions', function (Blueprint $table) {
            $table->dropColumn(['location_type', 'location_detail']);
        });
    }
};
