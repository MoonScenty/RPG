<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mz_project/data/Animations.json에서 그대로 임포트 - id 고정 유지(Skills.json의
     * animationId가 참조). MZ의 Effekseer 이펙트(effectName) 기반 신규 포맷이라
     * PixiJS 쪽에 대응하는 재생 엔진이 아직 없고, AnimationSeeder는 데이터만 채운다.
     */
    public function up(): void
    {
        Schema::create('mz_animations', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('name', 50);
            $table->string('effect_name', 100)->nullable();
            $table->unsignedTinyInteger('display_type')->default(0);
            $table->integer('offset_x')->default(0);
            $table->integer('offset_y')->default(0);
            $table->unsignedInteger('scale')->default(100);
            $table->unsignedInteger('speed')->default(100);
            $table->json('rotation')->nullable();
            $table->json('flash_timings')->nullable();
            $table->json('sound_timings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mz_animations');
    }
};
