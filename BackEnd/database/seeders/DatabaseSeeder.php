<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->clearBattleSessions();

        // 각 시더가 자기 테이블만 delete()+insert()로 재시딩하다 보니, 두 번째
        // 이후 실행(db:seed 재실행)에서는 예를 들어 ClassSeeder가 mz_classes를
        // 지우는 시점에 이전 실행이 만들어둔 mz_actors/mz_skills가 여전히
        // class_id로 그걸 참조하고 있어 FK 에러가 난다. 시더 간 삭제 순서를 일일이
        // 맞추는 대신 시딩 구간 전체를 FK 체크 비활성화로 감싼다.
        Schema::disableForeignKeyConstraints();
        try {
            // RPGProject/data 기준 재작성(2026-08) - ClassSeeder가 제일 먼저(SkillSeeder의
            // class_id/learn_level 역산, ActorSeeder의 rawStatsAtLevel이 참조).
            // StateSeeder는 SkillSeeder/ItemSeeder보다 먼저(둘 다 상태 이름 존재 검증에
            // States 테이블 대신 직접 States.json을 다시 읽으므로 순서 자체는 무관하지만
            // 관례상 앞에 둔다). AnimationSeeder는 EnemySeeder의 <AttackAnimation> 태그
            // 조회(mz_animations.effect_name)가 필요해서 EnemySeeder보다 먼저 와야 한다.
            // WeaponSeeder/ArmorSeeder는 ActorSeeder(equips 참조)보다 먼저.
            $this->call(TypesAndSystemAudioSeeder::class);
            $this->call(ClassSeeder::class);
            $this->call(StateSeeder::class);
            $this->call(SkillSeeder::class);
            $this->call(WeaponSeeder::class);
            $this->call(ArmorSeeder::class);
            $this->call(ItemSeeder::class);
            $this->call(AnimationSeeder::class);
            $this->call(EnemySeeder::class);
            $this->call(TroopSeeder::class);
            $this->call(ActorSeeder::class);
            // ActorSeeder가 units.mz_actor_id를 채운 뒤에 실행해야 이 유닛들의
            // Actors.json 기본 무기를 조회할 수 있다 - 장비 시스템이 생기기 전에 이미
            // 영입된 기존 계정의 user_mercenaries.weapon_id를 소급으로 채워준다.
            $this->call(EquipmentBackfillSeeder::class);
        } finally {
            Schema::enableForeignKeyConstraints();
        }
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
