<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 전투 시작 시점의 스탯 스냅샷 - units 원본이 나중에 바뀌어도 진행 중인
     * 전투에는 영향 없음. slot은 진형과 동일 컨벤션(1-3 전열, 4-6 후열).
     */
    public function up(): void
    {
        Schema::create('battle_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained();
            $table->enum('side', ['ally', 'enemy']);
            $table->unsignedTinyInteger('slot');
            $table->unsignedInteger('max_hp');
            $table->unsignedInteger('max_mp');
            $table->unsignedInteger('atk');
            $table->unsignedInteger('def');
            $table->unsignedInteger('spd');
            $table->unsignedInteger('current_hp');
            $table->unsignedInteger('current_mp');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battle_units');
    }
};
