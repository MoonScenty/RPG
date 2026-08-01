<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Schema::change()는 doctrine/dbal이 있어야 해서(이 프로젝트엔 안 깔려있음)
        // raw SQL로 기본값만 바꾼다.
        DB::statement('ALTER TABLE users MODIFY max_fatigue INT UNSIGNED NOT NULL DEFAULT 240');
        DB::table('users')->update(['fatigue' => 240, 'max_fatigue' => 240]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('diamond');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('diamond')->default(0)->after('honor_points');
        });

        DB::statement('ALTER TABLE users MODIFY max_fatigue INT UNSIGNED NOT NULL DEFAULT 100');
    }
};
