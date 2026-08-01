<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 한 턴에 한 행동 = 한 행. damage는 signed로 시작 - unsigned로 뒀다가 힐 스킬(음수)에서 터진 전례가 있음. */
    public function up(): void
    {
        Schema::create('battle_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('turn_number');
            $table->foreignId('actor_battle_unit_id')->constrained('battle_units');
            $table->foreignId('target_battle_unit_id')->nullable()->constrained('battle_units');
            $table->enum('action_type', ['attack', 'skill', 'cancel']);
            $table->integer('damage')->nullable();
            $table->integer('target_hp_after')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battle_logs');
    }
};
