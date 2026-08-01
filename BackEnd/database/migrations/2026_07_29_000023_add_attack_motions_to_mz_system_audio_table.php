<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * System.json의 attackMotions(무기 종류별 기본 공격 모션 - wtypeId로 색인되는
     * {type, weaponImageId} 배열)도 이 테이블에 같이 보관한다. battleBgm 등과 마찬가지로
     * 프로젝트 전역 설정 1개뿐이라 "audio" 테이블이지만 여기 얹었다(별도 테이블을
     * 만들 만큼 크지 않음). type: 0=thrust, 1=swing, 2=missile(rmmz_objects.js
     * Game_Actor.performAttack() 기준).
     */
    public function up(): void
    {
        Schema::table('mz_system_audio', function (Blueprint $table) {
            $table->json('attack_motions')->nullable()->after('defeat_me');
        });
    }

    public function down(): void
    {
        Schema::table('mz_system_audio', function (Blueprint $table) {
            $table->dropColumn('attack_motions');
        });
    }
};
