<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Classes.json의 note에 붙는 <PartyHudIcon: n> 태그(n = IconSet.png 아이콘
 * 인덱스) - 파티 HUD 카드 우측 상단에 그 유닛 직업 아이콘으로 그린다
 * (MzNoteTagParser::parseClassTags(), ClassSeeder, BattleEngine::getState() 참고).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mz_classes', function (Blueprint $table) {
            $table->unsignedSmallInteger('party_hud_icon')->nullable()->after('traits');
        });
    }

    public function down(): void
    {
        Schema::table('mz_classes', function (Blueprint $table) {
            $table->dropColumn('party_hud_icon');
        });
    }
};
