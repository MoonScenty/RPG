<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 용병 개인별 장비 슬롯 - weapon(무기)/shield(방패)/body_armor(몸)/accessory(장신구).
 * MZ 표준 equip 슬롯 중 head(머리)는 이 프로젝트 방어구 데이터에 아예 쓰이지 않아서
 * (Armors.json에 etypeId=3인 항목이 없음) 슬롯을 만들지 않는다.
 *
 * mz_weapons/mz_armors 카탈로그 자체는 user_weapons/user_armors(quantity 스택)로
 * "보유"를 추적하는데, 장비는 그 풀에서 1개를 "예약"해서 빼놓는 개념이다 - 장착 시
 * quantity를 1 감소, 해제/교체 시 이전 장비를 quantity 1 증가로 되돌린다
 * (EquipmentController 참고). 그래서 이 컬럼들은 "그 무기/방어구를 지금 이
 * 용병이 쓰고 있다"는 상태만 가리키고, 실제 재고 수량은 여전히 user_weapons/
 * user_armors가 유일한 출처다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_mercenaries', function (Blueprint $table) {
            $table->unsignedSmallInteger('weapon_id')->nullable()->after('unit_id');
            $table->unsignedSmallInteger('shield_id')->nullable()->after('weapon_id');
            $table->unsignedSmallInteger('body_armor_id')->nullable()->after('shield_id');
            $table->unsignedSmallInteger('accessory_id')->nullable()->after('body_armor_id');

            $table->foreign('weapon_id')->references('id')->on('mz_weapons')->nullOnDelete();
            $table->foreign('shield_id')->references('id')->on('mz_armors')->nullOnDelete();
            $table->foreign('body_armor_id')->references('id')->on('mz_armors')->nullOnDelete();
            $table->foreign('accessory_id')->references('id')->on('mz_armors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('user_mercenaries', function (Blueprint $table) {
            $table->dropForeign(['weapon_id']);
            $table->dropForeign(['shield_id']);
            $table->dropForeign(['body_armor_id']);
            $table->dropForeign(['accessory_id']);
            $table->dropColumn(['weapon_id', 'shield_id', 'body_armor_id', 'accessory_id']);
        });
    }
};
