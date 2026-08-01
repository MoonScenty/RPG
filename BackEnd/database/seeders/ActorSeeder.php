<?php

namespace Database\Seeders;

use App\Models\MzClass;
use App\Models\Unit;
use App\Support\StatFormula;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * RPGProject/data/Actors.json -> mz_actors(원본 그대로 보관) + units(type=ally,
 * 실제 게임이 쓰는 파생 데이터). HP/MP/ATK/DEF/MAT/MDF/SPD/LUK은 classId로
 * mz_classes를 찾아 initialLevel 시점의 원시 스탯(rawStatsAtLevel)을
 * StatFormula::deriveCombatStats()로 변환한 "맨손" 베이스라인이다 - 실제 장비
 * 보너스는 BattleEngine::spawn()이 전투 스폰 시점에 유저 장비 기준으로 매번
 * 계산해서 얹는다(equipmentStatsFor() 참고, 여기선 손대지 않음).
 */
class ActorSeeder extends Seeder
{
    private const DATA_PATH = __DIR__ . '/../../../RPGProject/data';

    public function run(): void
    {
        $actors = $this->readJson('Actors.json');

        $this->importRawActors($actors);

        $count = 0;
        foreach ($actors as $actor) {
            $class = MzClass::find($actor['classId']);
            if ($class === null) {
                throw new \RuntimeException(
                    "액터 '{$actor['name']}'(id {$actor['id']})가 존재하지 않는 classId {$actor['classId']}를 참조합니다."
                    . ' ClassSeeder를 먼저 실행했는지 확인하세요.',
                );
            }

            $stats = StatFormula::deriveCombatStats($class->rawStatsAtLevel($actor['initialLevel']));

            Unit::updateOrCreate(
                ['sprite' => $this->faceSprite($actor['battlerName']), 'type' => 'ally'],
                [
                    'name' => $actor['name'],
                    'class_id' => $actor['classId'],
                    'mz_actor_id' => $actor['id'],
                    'attack_motion' => 'thrust',
                    'weapon_effect_name' => null,
                    'equip_traits' => [],
                    ...$stats,
                ],
            );
            $count++;
        }

        $this->command?->info("용병 {$count}명 시딩 완료 (Actors.json 기반).");
    }

    /**
     * battlerName("Actor1_1"~"Actor2_8")을 FrontEnd/src/lib/portrait.ts와
     * battle/characters.ts가 기대하는 "얼굴시트:index" 형식("Actor1:0")으로 변환한다.
     * RPGProject/data에는 별도 faceIndex가 없고 battlerName=faceName이 8명씩
     * 묶인 시트 파일명+슬롯 번호를 그대로 인코딩하고 있다(예: Actor1_1 -> Actor1.png의
     * 1번째 얼굴). 'Enemy:{image}'(EnemySeeder)처럼 이름을 그대로 이어붙이면 안 되고
     * 반드시 시트/인덱스로 분해해야 한다.
     */
    private function faceSprite(string $battlerName): string
    {
        if (! preg_match('/^([A-Za-z]+\d+)_(\d+)$/', $battlerName, $m)) {
            throw new \RuntimeException("battlerName '{$battlerName}'이 'Actor1_1' 형식이 아닙니다.");
        }

        return "{$m[1]}:" . ((int) $m[2] - 1);
    }

    private function importRawActors(array $actors): void
    {
        DB::table('mz_actors')->delete();

        $rows = [];
        foreach ($actors as $actor) {
            $rows[] = [
                'id' => $actor['id'],
                'name' => $actor['name'],
                'note' => $actor['note'] ?: null,
                'battler_name' => $actor['battlerName'] ?: null,
                'class_id' => $actor['classId'],
                'face_name' => $actor['faceName'] ?: null,
                'initial_level' => $actor['initialLevel'],
                'max_level' => $actor['maxLevel'],
                'profile' => $actor['profile'] ?: null,
                'equips' => json_encode($actor['equips'] ?? []),
                'traits' => json_encode($actor['traits'] ?? []),
            ];
        }
        DB::table('mz_actors')->insert($rows);
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
