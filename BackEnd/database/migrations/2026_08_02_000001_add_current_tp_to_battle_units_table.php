<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TP(전술 포인트, MZ 표준 0~100 스탯) - 최대치는 항상 100이라 별도 컬럼 없이
 * 현재값만 저장한다. 파티 HUD의 점 5개 인디케이터가 20TP당 1칸씩 채워진다
 * (BattleEngine::gainTp() 참고).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battle_units', function (Blueprint $table) {
            $table->unsignedTinyInteger('current_tp')->default(0)->after('atb_gauge');
        });
    }

    public function down(): void
    {
        Schema::table('battle_units', function (Blueprint $table) {
            $table->dropColumn('current_tp');
        });
    }
};
