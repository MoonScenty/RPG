<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 광역기(scope 2/8/10) 전용 - 대상이 여럿이라 기존 target_battle_unit_id/damage/
 * target_hp_after(단일 컬럼)로는 표현이 안 돼서, 대상별 결과를
 * [{battle_unit_id, damage, hp_after}, ...] 배열로 이 컬럼 하나에 담는다.
 * 단일 대상 행은 그대로 기존 컬럼을 쓰고 이 컬럼은 null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battle_logs', function (Blueprint $table) {
            $table->json('targets')->nullable()->after('target_hp_after');
        });
    }

    public function down(): void
    {
        Schema::table('battle_logs', function (Blueprint $table) {
            $table->dropColumn('targets');
        });
    }
};
