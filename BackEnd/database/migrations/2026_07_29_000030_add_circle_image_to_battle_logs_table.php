<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * <CircleImage: 배율, 이름> 스킬 노트태그(캐스팅 스킬 전용) - 캐스팅 턴마다
 * BattleEngine::log()가 시전 중인 스킬의 태그를 읽어 로그 행에 그대로 박아둔다
 * (읽을 때마다 mz_skills를 다시 조회할 필요 없게). 이름은 FrontEnd/public/assets/circle/
 * {이름}.png 파일명, 배율은 1이 원본 크기(퍼센트 아님 - Pixi의 scale.set()에 그대로 씀).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battle_logs', function (Blueprint $table) {
            $table->string('circle_image_name', 100)->nullable()->after('target_hp_after');
            $table->float('circle_image_scale')->nullable()->after('circle_image_name');
        });
    }

    public function down(): void
    {
        Schema::table('battle_logs', function (Blueprint $table) {
            $table->dropColumn(['circle_image_name', 'circle_image_scale']);
        });
    }
};
