<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * <GaugeBoost: n> 실제 구현을 위한 진짜 ATB 게이지 - 매 advanceTurn() 호출마다
 * 살아있는 유닛 전원의 게이지를 spd만큼 틱 증가시키다가 100에 먼저 도달하는
 * 유닛이 행동한다(BattleEngine::pickReadyActor()). 기존 "spd 내림차순 + 턴 순환"
 * 라운드로빈을 대체한다 - 그 방식은 매 사이클 전원이 정확히 한 번씩 행동했지만,
 * 진짜 ATB는 spd가 높을수록 더 자주 행동한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battle_units', function (Blueprint $table) {
            $table->float('atb_gauge')->default(0)->after('skill_cooldowns');
        });
    }

    public function down(): void
    {
        Schema::table('battle_units', function (Blueprint $table) {
            $table->dropColumn('atb_gauge');
        });
    }
};
