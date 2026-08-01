<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * <SkillMotion: 모션이름> 스킬 노트태그 - circle_image_name과 동일한 패턴으로,
 * 스킬을 쓴 턴마다 BattleEngine::log()가 그 스킬의 태그를 로그 행에 그대로
 * 박아둔다(프론트가 mz_skills를 다시 조회할 필요 없이). 값은 sv 배틀러의 18개
 * MZ 표준 모션 이름 중 하나(swing/thrust/missile/spell/skill/chant 등) -
 * 없으면(null) 프론트가 기존처럼 swing으로 폴백한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battle_logs', function (Blueprint $table) {
            $table->string('skill_motion', 50)->nullable()->after('circle_image_scale');
        });
    }

    public function down(): void
    {
        Schema::table('battle_logs', function (Blueprint $table) {
            $table->dropColumn('skill_motion');
        });
    }
};
