<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 단일 대상 공격/스킬(attack, skill damage_type=1)의 치명타 발동 여부. 광역기는
 * 대상마다 각각 굴리므로 여기 대신 battle_logs.targets 배열의 각 항목에
 * critical 키로 들어간다(BattleEngine::resolveAoeSkillUse()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battle_logs', function (Blueprint $table) {
            $table->boolean('is_critical')->nullable()->after('damage');
        });
    }

    public function down(): void
    {
        Schema::table('battle_logs', function (Blueprint $table) {
            $table->dropColumn('is_critical');
        });
    }
};
