<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 계정(용병단) 단위 무기 인벤토리 - user_items와 동일 패턴. 아직 이 인벤토리를
 * 특정 용병에게 "장착"하는 시스템은 없다(ActorSeeder가 시딩 시점에 초기 무기를
 * 고정 스냅샷하는 방식은 그대로 유지) - 지금은 소유/재고만 추적한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_weapons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('mz_weapon_id');
            $table->foreign('mz_weapon_id')->references('id')->on('mz_weapons')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->unique(['user_id', 'mz_weapon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_weapons');
    }
};
