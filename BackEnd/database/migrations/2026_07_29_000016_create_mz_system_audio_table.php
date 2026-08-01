<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mz_project/data/System.json의 battleBgm/victoryMe/defeatMe를 그대로 임포트 -
     * 트룹별이 아니라 프로젝트 전역 설정 1행(id=1 고정)만 존재한다(battleback과 동일한
     * System.json 전역 기본값 패턴).
     */
    public function up(): void
    {
        Schema::create('mz_system_audio', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('battle_bgm', 50)->nullable();
            $table->string('victory_me', 50)->nullable();
            $table->string('defeat_me', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mz_system_audio');
    }
};
