<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 단일 대상 공격/스킬의 명중 판정 결과('hit'/'missed'/'evaded') - BattleEngine::rollHit()
 * 참고. 광역기는 대상마다 각각 굴리므로 여기 대신 battle_logs.targets 배열의 각
 * 항목에 hit_outcome 키로 들어간다. 캐스팅/취소 등 판정 자체가 없는 행은 null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battle_logs', function (Blueprint $table) {
            $table->string('hit_outcome', 10)->nullable()->after('is_critical');
        });
    }

    public function down(): void
    {
        Schema::table('battle_logs', function (Blueprint $table) {
            $table->dropColumn('hit_outcome');
        });
    }
};
