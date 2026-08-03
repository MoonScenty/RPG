<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * party_hud_icon(파티 HUD 카드 우측 상단에 직업 아이콘을 그리는 용도로 추가했던
 * 필드)은 실제로는 PartyHud.ts 재디자인 때 아이콘 표시 자체를 뺐는데(사용자 지시)
 * 데이터 파이프라인만 안 지워지고 계속 흘러다니고 있었다 - 아무 데도 안 쓰이는
 * 죽은 필드라 사용자 지시로 완전히 제거한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mz_classes', function (Blueprint $table) {
            $table->dropColumn('party_hud_icon');
        });
    }

    public function down(): void
    {
        Schema::table('mz_classes', function (Blueprint $table) {
            $table->integer('party_hud_icon')->nullable();
        });
    }
};
