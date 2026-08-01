<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * RPGProject/data/Types.json -> mz_types(id=1 고정 1행) - elementId/skillTypeId/
 * weaponTypeId/armorTypeId/equipTypeId가 가리키는 이름 목록.
 *
 * mz_system_audio는 예전엔 mz_project/data/System.json(전역 전투 BGM 기본값 +
 * 무기 타입별 공격 모션 매핑)에서 왔는데, RPGProject엔 대응하는 파일이 없다(전투
 * BGM은 이제 Troops.json 트룹별로 있고, 무기 타입별 모션은 RPGEditor에 입력 UI가
 * 없다) - 무기 타입 7종(검/활/지팡이/홀장/창/책/오브)에 대해 그럴듯한 기본 모션만
 * 하드코딩해서 채워둔다(전부 thrust로 시작하는 것보다는 검=swing 등으로 구분).
 */
class TypesAndSystemAudioSeeder extends Seeder
{
    private const DATA_PATH = __DIR__ . '/../../../RPGProject/data';

    /** 무기 유형(Types.json weaponTypes 순서: 검/활/지팡이/홀장/창/책/오브) -> attackMotions[wtypeId].type(0=thrust/1=swing/2=missile). */
    private const WEAPON_TYPE_MOTION = [0 => 1, 1 => 2, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];

    public function run(): void
    {
        $types = $this->readJson('Types.json');

        DB::table('mz_types')->delete();
        DB::table('mz_types')->insert([
            'id' => 1,
            'elements' => json_encode($types['elements']),
            'skill_types' => json_encode($types['skillTypes']),
            'weapon_types' => json_encode($types['weaponTypes']),
            'armor_types' => json_encode($types['armorTypes']),
            'equip_types' => json_encode($types['equipTypes']),
        ]);

        $attackMotions = [];
        // MZ attackMotions는 wtypeId=1부터 시작(0은 "맨손"=thrust 고정) - Types.json의
        // weaponTypeId(0-based)에 +1.
        $attackMotions[0] = ['type' => 0];
        foreach (self::WEAPON_TYPE_MOTION as $wtypeId => $motionType) {
            $attackMotions[$wtypeId + 1] = ['type' => $motionType];
        }

        DB::table('mz_system_audio')->delete();
        DB::table('mz_system_audio')->insert([
            'id' => 1,
            'battle_bgm' => null,
            'victory_me' => null,
            'defeat_me' => null,
            'attack_motions' => json_encode($attackMotions),
        ]);

        $this->command?->info('mz_types/mz_system_audio 임포트 완료.');
    }

    private function readJson(string $file): array
    {
        $path = self::DATA_PATH . '/' . $file;
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("RPGProject 데이터를 읽지 못했습니다: {$path}");
        }

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
