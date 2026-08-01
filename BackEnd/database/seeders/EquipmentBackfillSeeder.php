<?php

namespace Database\Seeders;

use App\Models\UserMercenary;
use Illuminate\Database\Seeder;

/**
 * user_mercenaries.weapon_id 등 장비 슬롯은 이 시스템이 생기기 전에 이미 영입된
 * 기존 계정엔 전부 NULL로 남아있다 - MercenaryController::purchase()의 기본 무기
 * 자동 장착(Unit::defaultWeaponId())은 신규 영입 시점에만 실행되고, 이미 만들어진
 * user_mercenaries 행을 소급해서 채워주진 않기 때문. 매번 db:seed를 돌려도 안전하게
 * (weapon_id가 이미 있으면 절대 안 건드리고, null인 것만 채움) 이 빈틈을 메운다.
 */
class EquipmentBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $count = 0;

        UserMercenary::whereNull('weapon_id')->with('unit')->chunkById(200, function ($mercenaries) use (&$count) {
            foreach ($mercenaries as $mercenary) {
                $weaponId = $mercenary->unit?->defaultWeaponId();
                if ($weaponId === null) {
                    continue;
                }
                $mercenary->weapon_id = $weaponId;
                $mercenary->save();
                $count++;
            }
        });

        $this->command?->info("기존 보유 용병 {$count}명에게 기본 무기를 채워 넣었습니다.");
    }
}
