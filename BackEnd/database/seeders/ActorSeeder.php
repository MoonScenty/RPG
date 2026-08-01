<?php

namespace Database\Seeders;

use App\Models\MzClass;
use App\Models\MzSystemAudio;
use App\Models\Unit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * mz_project/data/Actors.json -> mz_actors(원본 그대로 보관, mz_enemies와 동일한
 * 패턴) + units(type=ally, 실제 게임이 쓰는 파생 데이터). 이름/직업/초상화·배틀러
 * 스프라이트는 Actors.json에서 그대로 가져오고, HP/MP/ATK/DEF/MAT/MDF/SPD는 해당
 * 액터의 classId로 mz_classes를 찾아 initialLevel 시점 스탯(MzClass::statAtLevel())을
 * 그대로 쓴다 - 예전엔 여기에 Actors.json의 초기 장비(equips) 보너스까지 얹어서
 * "항상 장착된 초기값"으로 스냅샷했지만, 용병소에 장비 탭이 생기면서 장비는 이제
 * 유저별로 바뀌는 값이라 units(모든 유저가 공유하는 카탈로그 행)에 박아둘 수 없다 -
 * units는 "맨손" 베이스라인만 갖고, 실제 장비 보너스/트레잇/공격모션은
 * EquipmentController가 관리하는 user_mercenaries 장비 슬롯을 통해
 * BattleEngine::spawn()이 전투 스폰 시점에 매번 계산한다(equipmentStatsFor() 참고).
 *
 * mz_actor_id만 저장해두면 MercenaryController::purchase()가 그걸로 Actors.json의
 * 기본 장비(equips[0])를 찾아 갓 영입한 용병에게 자동으로 들려줄 수 있다.
 *
 * 예전 UnitSeeder처럼 손으로 스탯을 맞추지 않고 mz 원본 곡선을 그대로 쓰므로,
 * mz_project에 액터를 추가하고 재시딩만 하면 자동으로 반영된다.
 */
class ActorSeeder extends Seeder
{
    private const MZ_DATA_PATH = __DIR__ . '/../../../mz_project/data';

    public function run(): void
    {
        $actors = $this->readJson('Actors.json');
        $systemAudio = MzSystemAudio::current();
        // 맨손(장비 없음) 상태의 기본 공격 모션 - System.json attackMotions[0]과 동일.
        $unarmedMotion = $systemAudio?->motionForWeaponType(0) ?? 'thrust';

        $this->importRawActors($actors);

        $count = 0;
        foreach ($actors as $actor) {
            if ($actor === null) {
                continue;
            }

            $class = MzClass::find($actor['classId']);
            if ($class === null) {
                throw new \RuntimeException(
                    "액터 '{$actor['name']}'(id {$actor['id']})가 존재하지 않는 classId {$actor['classId']}를 참조합니다."
                    . ' ClassSeeder를 먼저 실행했는지 확인하세요.',
                );
            }

            $stats = $class->statAtLevel($actor['initialLevel']);

            Unit::updateOrCreate(
                ['sprite' => "{$actor['faceName']}:{$actor['faceIndex']}", 'type' => 'ally'],
                [
                    'name' => $actor['name'],
                    'class_id' => $actor['classId'],
                    'mz_actor_id' => $actor['id'],
                    'attack_motion' => $unarmedMotion,
                    'weapon_effect_name' => null,
                    'equip_traits' => [],
                    ...$stats,
                ],
            );
            $count++;
        }

        $this->command?->info("용병 {$count}명 시딩 완료 (Actors.json 기반).");
    }

    /** mz_enemies와 동일하게 원본 필드를 그대로 창고 테이블에 보관(FK 강제 없음). */
    private function importRawActors(array $actors): void
    {
        DB::table('mz_actors')->delete();

        $rows = [];
        foreach ($actors as $actor) {
            if ($actor === null) {
                continue;
            }
            $rows[] = [
                'id' => $actor['id'],
                'name' => $actor['name'],
                'class_id' => $actor['classId'],
                'character_name' => $actor['characterName'],
                'character_index' => $actor['characterIndex'],
                'face_name' => $actor['faceName'],
                'face_index' => $actor['faceIndex'],
                'battler_name' => $actor['battlerName'] ?: null,
                'initial_level' => $actor['initialLevel'],
                'max_level' => $actor['maxLevel'],
                'nickname' => $actor['nickname'] ?: null,
                'note' => $actor['note'] ?: null,
                'profile' => $actor['profile'] ?: null,
                'equips' => json_encode($actor['equips'] ?? []),
                'traits' => json_encode($actor['traits'] ?? []),
            ];
        }
        DB::table('mz_actors')->insert($rows);
    }

    private function readJson(string $file): array
    {
        $path = self::MZ_DATA_PATH . '/' . $file;
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("mz_project 데이터를 읽지 못했습니다: {$path}");
        }

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
