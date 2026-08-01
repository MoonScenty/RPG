<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 용병 1명당 5개의 스왑 가능한 행동 규칙 프리셋. 각 프리셋 안에는 규칙을
     * 원하는 만큼(priority 순서) 넣을 수 있다 - "프리셋 5개"이지 "규칙 최대
     * 5개"가 아님. 어느 프리셋이 쓰이는지는 user_mercenaries.active_gambit_preset.
     */
    public function up(): void
    {
        Schema::create('mercenary_gambits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_mercenary_id')->constrained('user_mercenaries')->cascadeOnDelete();
            $table->unsignedTinyInteger('preset_number');
            $table->unsignedInteger('priority');

            $table->string('condition_kind', 20);
            $table->string('condition_scope', 10)->nullable();
            $table->string('condition_stat', 10)->nullable();
            $table->string('condition_value_type', 10)->nullable();
            $table->string('condition_operator', 10)->nullable();
            $table->unsignedInteger('condition_value')->nullable();
            $table->string('condition_debuff_key', 40)->nullable();

            $table->string('action_type', 10);
            $table->string('action_skill_key', 40)->nullable();

            // 기본 자동생성 이름(mercenary_gambits_user_mercenary_id_preset_number_priority_unique)이
            // MySQL 식별자 64자 제한을 넘어서 짧은 이름을 직접 지정한다.
            $table->unique(['user_mercenary_id', 'preset_number', 'priority'], 'gambit_preset_priority_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercenary_gambits');
    }
};
