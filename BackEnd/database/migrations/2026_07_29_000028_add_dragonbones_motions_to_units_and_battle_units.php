<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * <DragonBonesMotion: 모션이름, 클립이름> 노트태그(반복 가능)를 모아 만든
 * {모션이름: 클립이름} 맵. 스켈레톤마다 클립 이름이 제각각이라(예: "Attack A") 우리
 * 내부 모션 이름(idle/damage/swing/...)과 직접 연결해줘야 한다 - 매핑에 없는
 * 모션은 sv 배틀러가 없는 포즈를 건너뛰는 것과 동일하게 조용히 무시된다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->json('dragonbones_motions')->nullable()->after('dragonbones_atlas');
        });

        Schema::table('battle_units', function (Blueprint $table) {
            $table->json('dragonbones_motions')->nullable()->after('dragonbones_atlas');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('dragonbones_motions');
        });

        Schema::table('battle_units', function (Blueprint $table) {
            $table->dropColumn('dragonbones_motions');
        });
    }
};
