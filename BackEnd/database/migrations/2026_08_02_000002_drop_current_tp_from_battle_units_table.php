<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 2026_08_02_000001에서 추가한 current_tp를 되돌린다 - 파티 HUD의 "Turn Point"
 * 점 5개는 알고 보니 MZ의 데미지 기반 TP가 아니라 이미 존재하는 atb_gauge(0~100)를
 * 5칸으로 환산해서 보여주는 것뿐이라 별도 저장 컬럼이 필요 없었다
 * (dots = round(atb_gauge / 100 * 5), PartyHud.ts 참고).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battle_units', function (Blueprint $table) {
            $table->dropColumn('current_tp');
        });
    }

    public function down(): void
    {
        Schema::table('battle_units', function (Blueprint $table) {
            $table->unsignedTinyInteger('current_tp')->default(0)->after('atb_gauge');
        });
    }
};
