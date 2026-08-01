<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 계정(용병단) 단위 소모 아이템 인벤토리 - 특정 용병 소유가 아니라 파티 전체가
 * 공유하는 재고다(gambit "아이템을 사용한다"는 어느 용병이든 이 재고에서 꺼내 씀).
 * 소모 아이템 카탈로그(GambitCatalog::allItems())는 계속 스킬처럼 "쓸 수 있는
 * 목록"을 그대로 보여주고(재고 0이어도 gambit 규칙 자체는 만들 수 있음 - MP
 * 부족해도 스킬 규칙은 만들 수 있는 것과 동일), 실제 재고 확인/차감은 전투 해석
 * 시점(BattleEngine::hasItemStock()/consumeItemStock())에서만 일어난다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('mz_item_id');
            $table->foreign('mz_item_id')->references('id')->on('mz_items')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->unique(['user_id', 'mz_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_items');
    }
};
