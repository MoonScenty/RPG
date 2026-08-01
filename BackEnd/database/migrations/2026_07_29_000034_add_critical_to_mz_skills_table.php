<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Skills.json의 네이티브 damage.critical 필드 - 이 스킬이 치명타를 낼 수 있는지. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mz_skills', function (Blueprint $table) {
            $table->boolean('critical')->default(false)->after('damage_type');
        });
    }

    public function down(): void
    {
        Schema::table('mz_skills', function (Blueprint $table) {
            $table->dropColumn('critical');
        });
    }
};
