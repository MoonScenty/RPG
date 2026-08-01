<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mz_project/data/Troops.json members[]에서 그대로 임포트. MZ 원본은 x/y 픽셀
     * 좌표로 배치하지만 우리 전투 화면은 6슬롯(전열1-3/후열4-6) 고정 레이아웃이라
     * position은 그 좌표 대신 members 배열 순서(1부터)를 그대로 슬롯 번호로 쓴다.
     */
    public function up(): void
    {
        Schema::create('mz_troop_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('troop_id');
            $table->foreign('troop_id')->references('id')->on('mz_troops')->cascadeOnDelete();
            $table->unsignedTinyInteger('enemy_id');
            $table->foreign('enemy_id')->references('id')->on('mz_enemies');
            $table->unsignedTinyInteger('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mz_troop_members');
    }
};
