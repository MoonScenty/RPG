<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 구매 가능한 용병(및 훗날 적) 카탈로그. 계정별 보유 여부는 user_mercenaries가 따로 관리. */
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->enum('type', ['ally', 'enemy']);
            $table->unsignedInteger('max_hp')->default(100);
            $table->unsignedInteger('max_mp')->default(0);
            $table->unsignedInteger('atk')->default(10);
            $table->unsignedInteger('def')->default(5);
            $table->unsignedInteger('spd')->default(10);
            // 초상화 크롭 좌표 - "Actor1:0" 형식 (얼굴 시트 파일명:faceIndex).
            $table->string('sprite', 150);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
