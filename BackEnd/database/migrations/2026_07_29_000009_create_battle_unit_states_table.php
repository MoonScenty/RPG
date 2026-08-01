<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 전투 중 한 battle_unit에게 걸린 상태(버프/디버프) 추적 - 이전엔 이 개념 자체가 없었다. */
    public function up(): void
    {
        Schema::create('battle_unit_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_unit_id')->constrained()->cascadeOnDelete();
            // foreignId()는 항상 unsignedBigInteger라 mz_states.id(unsignedSmallInteger)와
            // 타입이 안 맞아 FK 생성이 실패한다 - 타입을 맞춰서 직접 선언.
            $table->unsignedSmallInteger('state_id');
            $table->foreign('state_id')->references('id')->on('mz_states');
            $table->unsignedInteger('remaining_turns')->nullable();
            $table->unsignedInteger('applied_turn');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battle_unit_states');
    }
};
