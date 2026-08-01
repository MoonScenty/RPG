<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 일반 공격 시 재생할 SV 배틀러 모션 이름 - thrust/swing/missile 중 하나
     * (System.json attackMotions를 초기 장착 무기 wtypeId로 조회해서 ActorSeeder가
     * 정함, 무기가 없는 적은 기본값 swing). BattleEngine::spawn()이 units에서
     * battle_units로 그대로 복사한다(atk/def 등과 동일 패턴).
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->enum('attack_motion', ['thrust', 'swing', 'missile'])->default('swing')->after('sprite');
        });
        Schema::table('battle_units', function (Blueprint $table) {
            $table->enum('attack_motion', ['thrust', 'swing', 'missile'])->default('swing')->after('spd');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('attack_motion');
        });
        Schema::table('battle_units', function (Blueprint $table) {
            $table->dropColumn('attack_motion');
        });
    }
};
