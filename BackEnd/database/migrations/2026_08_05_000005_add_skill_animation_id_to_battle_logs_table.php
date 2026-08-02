<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 스킬 사용(action_type='skill') 턴에 재생할 mz_animations.id(Invocation.AnimationId,
 * RPGEditor 스킬 편집 화면의 "애니메이션" 필드) - skill_motion(캐릭터 포즈)과 별개로,
 * playWeaponEffect()가 그리는 실제 이펙트 스프라이트다. 지금까지는 일반 공격의
 * weapon_animation_id만 이 경로를 탔고 스킬은 캐릭터 모션만 재생하고 이펙트가
 * 아예 없었다(SkillSeeder가 invocation.animationId를 읽지 않던 누락). 없으면(null)
 * 이펙트 없이 모션만 재생(기존과 동일).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battle_logs', function (Blueprint $table) {
            $table->unsignedInteger('skill_animation_id')->nullable()->after('skill_motion');
        });
    }

    public function down(): void
    {
        Schema::table('battle_logs', function (Blueprint $table) {
            $table->dropColumn('skill_animation_id');
        });
    }
};
