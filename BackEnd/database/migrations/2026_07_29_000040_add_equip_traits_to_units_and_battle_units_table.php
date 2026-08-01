<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ActorSeeder가 Actors.json의 equips[0..4](무기+방어구 4슬롯) 전체를 읽어서
 * 각 장비의 traits[]를 합친 원본 배열을 그대로 스냅샷한다 - 지금까지는 무기(equips[0])
 * 하나만 스탯 보너스로 반영하고 방어구(equips[1..4])는 아예 무시했었다. BattleEngine::
 * traitSourcesFor()가 이 컬럼을 클래스/상태 트레잇과 같은 방식으로 합산한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->json('equip_traits')->nullable()->after('weapon_effect_name');
        });
        Schema::table('battle_units', function (Blueprint $table) {
            $table->json('equip_traits')->nullable()->after('weapon_effect_name');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('equip_traits');
        });
        Schema::table('battle_units', function (Blueprint $table) {
            $table->dropColumn('equip_traits');
        });
    }
};
