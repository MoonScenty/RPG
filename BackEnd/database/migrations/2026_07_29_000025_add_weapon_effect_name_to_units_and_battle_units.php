<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 일반 공격 시 대상에게 재생할 Effekseer 이펙트 파일명(mz_animations.effect_name,
     * 확장자 없음 - 예: "HitPhysical") - 초기 장착 무기(equips[0])의 animation_id로
     * mz_animations를 조회해서 ActorSeeder가 정한다. 값 없으면(무기가 없거나
     * animation_id가 0) 이펙트 재생 안 함. 적은 무기 개념이 없어 항상 null.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('weapon_effect_name', 100)->nullable()->after('attack_motion');
        });
        Schema::table('battle_units', function (Blueprint $table) {
            $table->string('weapon_effect_name', 100)->nullable()->after('attack_motion');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('weapon_effect_name');
        });
        Schema::table('battle_units', function (Blueprint $table) {
            $table->dropColumn('weapon_effect_name');
        });
    }
};
