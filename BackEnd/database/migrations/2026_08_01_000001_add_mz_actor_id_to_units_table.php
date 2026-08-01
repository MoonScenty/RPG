<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ActorSeeder가 mz_actors 원본 행을 units에 연결해두는 참조 컬럼 - 용병 구매 시
 * MercenaryController가 이걸로 Actors.json의 기본 장비(equips)를 찾아 자동
 * 장착시켜준다(용병소 장비 탭 도입으로 신규 구매 용병이 맨손으로 시작하게 됐는데,
 * 최소한 원래 데이터에 있던 기본 무기만큼은 그대로 들려주기 위함). 적(enemy)
 * 유닛에는 해당 없어 nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            // mz_actors.id는 unsignedTinyInteger(액터 16명이라 tinyint로 충분) - FK를
            // 걸려면 참조 컬럼과 타입이 정확히 일치해야 해서(다르면 errno 150) 맞춰준다.
            $table->unsignedTinyInteger('mz_actor_id')->nullable()->after('sprite');
            $table->foreign('mz_actor_id')->references('id')->on('mz_actors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropForeign(['mz_actor_id']);
            $table->dropColumn('mz_actor_id');
        });
    }
};
