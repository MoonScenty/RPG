<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * <DragonBonesScale: n>(퍼센트, mz_animations.scale과 동일한 관례) 노트태그 값.
 * FrontEnd/src/battle/BattleScene.ts가 bounding box 실측으로 자동 정규화한 크기 위에
 * 추가로 곱하는 배율 - null이면 100%(추가 조정 없음)로 취급.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->unsignedInteger('dragonbones_scale')->nullable()->after('dragonbones_motions');
        });

        Schema::table('battle_units', function (Blueprint $table) {
            $table->unsignedInteger('dragonbones_scale')->nullable()->after('dragonbones_motions');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('dragonbones_scale');
        });

        Schema::table('battle_units', function (Blueprint $table) {
            $table->dropColumn('dragonbones_scale');
        });
    }
};
