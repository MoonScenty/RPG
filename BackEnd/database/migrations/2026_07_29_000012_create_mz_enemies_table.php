<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** mz_project/data/Enemies.json에서 그대로 임포트 - id 고정 유지(Troops.json members[].enemyId가 참조). */
    public function up(): void
    {
        Schema::create('mz_enemies', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name', 50);
            $table->string('battler_name', 50);
            $table->unsignedInteger('mhp');
            $table->unsignedInteger('mmp');
            $table->unsignedInteger('atk');
            $table->unsignedInteger('def');
            $table->unsignedInteger('mat');
            $table->unsignedInteger('mdf');
            $table->unsignedInteger('agi');
            $table->unsignedInteger('luk');
            // MZ 원본 actions 배열 그대로 보관(conditionType/skillId/rating 등) - 지금은
            // "항상 skillId=1(공격)" 하나뿐이라 BattleEngine이 참조하지 않지만, 나중에
            // 적 AI를 조건부로 확장할 때를 위해 원본을 보존해둔다.
            $table->json('actions');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mz_enemies');
    }
};
