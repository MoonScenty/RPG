<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * States.json 네이티브 지속시간 필드 - autoRemovalTiming(0=턴 수로는 안 사라짐,
 * 1/2=아래 min/maxTurns 사이 무작위 턴 수 후 자동 해제)/minTurns/maxTurns.
 * battle_unit_states.remaining_turns가 이 값을 근거로 채워진다(BattleEngine::applyState()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mz_states', function (Blueprint $table) {
            $table->unsignedTinyInteger('auto_removal_timing')->default(0)->after('is_buff');
            $table->unsignedTinyInteger('min_turns')->default(1)->after('auto_removal_timing');
            $table->unsignedTinyInteger('max_turns')->default(1)->after('min_turns');
        });
    }

    public function down(): void
    {
        Schema::table('mz_states', function (Blueprint $table) {
            $table->dropColumn(['auto_removal_timing', 'min_turns', 'max_turns']);
        });
    }
};
