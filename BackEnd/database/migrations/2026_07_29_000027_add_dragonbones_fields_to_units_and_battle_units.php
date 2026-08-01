<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * <DragonBonesData: 이름>/<DragonBonesTextureAtlasData: 이름> 노트태그(적 전용) 값을
 * 담는 컬럼. 값은 mz_project/img/dragonbones/{값}.json 파일명 자체(DB 데이터 참조가
 * 아니라 실제 파일 참조라 이름-참조 컨벤션과 무관 - MzNoteTagParser::parseEnemyTags()
 * 참고) - frontend/src/battle/dragonbones.ts가 이 이름으로 frontend/public/assets/dragonbones/의
 * 스켈레톤+텍스처 아틀라스를 fetch한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('dragonbones_skeleton', 100)->nullable()->after('weapon_effect_name');
            $table->string('dragonbones_atlas', 100)->nullable()->after('dragonbones_skeleton');
        });

        Schema::table('battle_units', function (Blueprint $table) {
            $table->string('dragonbones_skeleton', 100)->nullable()->after('weapon_effect_name');
            $table->string('dragonbones_atlas', 100)->nullable()->after('dragonbones_skeleton');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn(['dragonbones_skeleton', 'dragonbones_atlas']);
        });

        Schema::table('battle_units', function (Blueprint $table) {
            $table->dropColumn(['dragonbones_skeleton', 'dragonbones_atlas']);
        });
    }
};
