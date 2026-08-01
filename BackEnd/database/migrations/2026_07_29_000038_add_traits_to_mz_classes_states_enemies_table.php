<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Classes.json/States.json/Enemies.json의 네이티브 traits[](code 22 = xparam:
 * 명중/회피/치명타/치명타회피/마법회피 등)를 원본 그대로 보관 - mz_weapons/mz_armors는
 * 이미 이 컬럼이 있었다. BattleEngine::xparam()이 아군은 class traits, 적은 자기
 * traits + 현재 걸린 상태들의 traits를 합산해서 명중/회피/치명타 판정에 쓴다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mz_classes', function (Blueprint $table) {
            $table->json('traits')->nullable()->after('params');
        });
        Schema::table('mz_states', function (Blueprint $table) {
            $table->json('traits')->nullable()->after('max_turns');
        });
        Schema::table('mz_enemies', function (Blueprint $table) {
            $table->json('traits')->nullable()->after('actions');
        });
    }

    public function down(): void
    {
        Schema::table('mz_classes', function (Blueprint $table) {
            $table->dropColumn('traits');
        });
        Schema::table('mz_states', function (Blueprint $table) {
            $table->dropColumn('traits');
        });
        Schema::table('mz_enemies', function (Blueprint $table) {
            $table->dropColumn('traits');
        });
    }
};
