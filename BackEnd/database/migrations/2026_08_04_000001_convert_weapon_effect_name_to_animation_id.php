<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Effekseer 폐지 - 일반 공격 시 재생할 애니메이션을 파일명(weapon_effect_name)
     * 대신 mz_animations.id(Animations.json 원본 ID)로 직접 가리킨다. 무기의
     * animation_id를 그대로 저장하면 되므로 mz_animations 조회 없이 시딩 시점에
     * 바로 대입 가능해졌다.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('weapon_effect_name');
        });
        Schema::table('units', function (Blueprint $table) {
            $table->unsignedSmallInteger('weapon_animation_id')->nullable()->after('attack_motion');
        });

        Schema::table('battle_units', function (Blueprint $table) {
            $table->dropColumn('weapon_effect_name');
        });
        Schema::table('battle_units', function (Blueprint $table) {
            $table->unsignedSmallInteger('weapon_animation_id')->nullable()->after('attack_motion');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('weapon_animation_id');
        });
        Schema::table('units', function (Blueprint $table) {
            $table->string('weapon_effect_name', 100)->nullable()->after('attack_motion');
        });

        Schema::table('battle_units', function (Blueprint $table) {
            $table->dropColumn('weapon_animation_id');
        });
        Schema::table('battle_units', function (Blueprint $table) {
            $table->string('weapon_effect_name', 100)->nullable()->after('attack_motion');
        });
    }
};
