<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** <Casting: n> 대기 상태 + <Cooldown: n> 스킬별 재사용 대기시간 추적. */
    public function up(): void
    {
        Schema::table('battle_units', function (Blueprint $table) {
            // foreignId()는 항상 unsignedBigInteger라 mz_skills.id(unsignedSmallInteger)와
            // 타입이 안 맞아 FK 생성이 실패한다 - 타입을 맞춰서 직접 선언.
            $table->unsignedSmallInteger('casting_skill_id')->nullable();
            $table->foreign('casting_skill_id')->references('id')->on('mz_skills')->nullOnDelete();
            $table->foreignId('casting_target_battle_unit_id')->nullable()->constrained('battle_units')->nullOnDelete();
            $table->unsignedInteger('casting_turns_remaining')->nullable();
            // {skill_id: 그 스킬이 다시 사용 가능해지는 battle.turn_number}
            $table->json('skill_cooldowns')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('battle_units', function (Blueprint $table) {
            $table->dropForeign(['casting_skill_id']);
            $table->dropColumn('casting_skill_id');
            $table->dropConstrainedForeignId('casting_target_battle_unit_id');
            $table->dropColumn(['casting_turns_remaining', 'skill_cooldowns']);
        });
    }
};
