<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mz_project/data/Weapons.json에서 그대로 임포트 - id 고정 유지(actors[].equips가
     * 참조). WeaponSeeder가 채운다. 아직 장착/전투 반영은 없고(용병소에 장비 시스템
     * 자체가 없음) 데이터만 원본 그대로 보관한다.
     */
    public function up(): void
    {
        Schema::create('mz_weapons', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('name', 50);
            $table->unsignedTinyInteger('wtype_id')->default(0);
            $table->unsignedTinyInteger('etype_id')->default(0);
            $table->unsignedSmallInteger('animation_id')->default(0);
            $table->unsignedSmallInteger('icon_index')->default(0);
            $table->unsignedInteger('price')->default(0);
            $table->text('description')->nullable();
            $table->text('note')->nullable();
            // params: [MHP,MMP,ATK,DEF,MAT,MDF,AGI,LUK] 장비 스탯 보너스(MZ 표준 순서).
            $table->json('params');
            // traits 원본 그대로 보관(code 31=속성 부여 등) - 아직 해석 안 함.
            $table->json('traits');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mz_weapons');
    }
};
