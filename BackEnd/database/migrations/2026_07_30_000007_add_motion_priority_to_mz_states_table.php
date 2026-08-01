<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * States.json 네이티브 motion(0=없음/1=abnormal/2=sleep/3=dead)/priority 필드 -
 * BattleEngine::stateMotionFor()가 이 값으로 SV 배틀러의 대기 포즈를 결정한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mz_states', function (Blueprint $table) {
            $table->unsignedTinyInteger('motion')->default(0)->after('max_turns');
            $table->unsignedInteger('priority')->default(0)->after('motion');
        });
    }

    public function down(): void
    {
        Schema::table('mz_states', function (Blueprint $table) {
            $table->dropColumn(['motion', 'priority']);
        });
    }
};
