<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mz_project/data/Armors.json에서 그대로 임포트 - id 고정 유지(actors[].equips가
     * 참조). mz_weapons와 동일한 패턴(장비 시스템 자체가 없어 아직 게임플레이 미반영,
     * 데이터만 원본 그대로 보관).
     */
    public function up(): void
    {
        Schema::create('mz_armors', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('name', 50);
            $table->unsignedTinyInteger('atype_id')->default(0);
            $table->unsignedTinyInteger('etype_id')->default(0);
            $table->unsignedSmallInteger('icon_index')->default(0);
            $table->unsignedInteger('price')->default(0);
            $table->text('description')->nullable();
            $table->text('note')->nullable();
            // params: [MHP,MMP,ATK,DEF,MAT,MDF,AGI,LUK] 장비 스탯 보너스(MZ 표준 순서).
            $table->json('params');
            $table->json('traits');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mz_armors');
    }
};
