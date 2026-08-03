<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * States.json isDebuff(RPGEditor 상태 편집 화면 "디버프" 체크박스) - "아군 디버프
 * 전부 해제" 계열 스킬(CleanseAllyDebuffs 태그, SkillSeeder 참고)이 어떤 상태를
 * 지울지 판정하는 데 쓴다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mz_states', function (Blueprint $table) {
            $table->boolean('is_debuff')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('mz_states', function (Blueprint $table) {
            $table->dropColumn('is_debuff');
        });
    }
};
