<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->clearBattleSessions();

        // ActorSeeder가 mz_classes.params(직업별 스탯 곡선)를 참조하므로 ClassSeeder가
        // 먼저 실행돼야 한다(무기 보너스는 더 이상 units에 안 박히지만, WeaponSeeder는
        // EquipmentBackfillSeeder가 참조할 mz_weapons를 채워야 해서 여전히 ActorSeeder
        // 전에 필요). MzImportSeeder는 Classes.json을 직접 읽어(스킬 class_id 역산용)
        // 순서 의존성은 없지만 관례상 같이 앞에 둔다. ArmorSeeder/ItemSeeder/
        // AnimationSeeder는 다른 시더와 참조 관계가 없어 순서 무관.
        $this->call(ClassSeeder::class);
        $this->call(WeaponSeeder::class);
        $this->call(ArmorSeeder::class);
        $this->call(ItemSeeder::class);
        $this->call(AnimationSeeder::class);
        $this->call(MzImportSeeder::class);
        $this->call(ActorSeeder::class);
        // ActorSeeder가 units.mz_actor_id를 채운 뒤에 실행해야 이 유닛들의
        // Actors.json 기본 무기를 조회할 수 있다 - 장비 시스템이 생기기 전에 이미
        // 영입된 기존 계정의 user_mercenaries.weapon_id를 소급으로 채워준다.
        $this->call(EquipmentBackfillSeeder::class);
    }

    /**
     * battle_units.unit_id는 RESTRICT라 지나간 전투 기록이 남아있으면 units를
     * upsert할 때(스탯이 바뀌는 경우) 걸릴 수 있다 - 전투 기록은 어차피 보존할
     * 가치가 없는 임시 데이터라 재시딩할 때마다 통째로 비운다. user_mercenaries/
     * formation_slots는 CASCADE라(플레이어가 실제로 보유한 용병/진형) 여기서 손대지
     * 않음 - 그래서 units 자체는 계속 upsert로 처리(id 유지가 필요).
     *
     * battle_logs -> battle_units는 RESTRICT라 먼저 지워야 하고, battles를 지우면
     * battle_units(cascadeOnDelete)와 그 아래 battle_unit_states까지 연쇄로 비워진다.
     */
    private function clearBattleSessions(): void
    {
        DB::table('battle_logs')->delete();
        DB::table('battles')->delete();
    }
}
