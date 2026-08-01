<?php

namespace Database\Seeders;

use App\Models\MzAnimation;
use App\Models\Unit;
use App\Support\MzNoteTagParser;
use App\Support\StatFormula;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * RPGProject/data/Enemies.json -> mz_enemies(원본 보관) + units(type=enemy). id는
 * 원본과 1:1 고정 유지(Troops.json 6슬롯, mz_troop_members 대신 직접 참조).
 *
 * MZ 8스탯(mhp/mmp/atk/def/mat/mdf/agi/luk) 대신 STR/VIT/MND/DEX/AGI/LUK/INT(+HP/MP)
 * 9스탯을 그대로 보관하고, StatFormula로 units의 파생 전투 스탯 + xparam 트레잇을
 * 계산해 넣는다. actions[]의 conditionType은 EnemyActionConditionType enum
 * (Always=0/TurnNumber=1/SelfHpRange=2/SelfMpRange=3/HasState=4)이 BattleEngine이
 * 기대하는 MZ 코드와 이미 순서가 같아 이름만 숫자로 바꾸면 된다.
 */
class EnemySeeder extends Seeder
{
    private const DATA_PATH = __DIR__ . '/../../../RPGProject/data';

    private const CONDITION_TYPE_MAP = [
        'always' => 0, 'turnNumber' => 1, 'selfHpRange' => 2, 'selfMpRange' => 3, 'hasState' => 4,
    ];

    public function run(): void
    {
        $enemies = $this->readJson('Enemies.json');

        DB::table('mz_enemies')->delete();

        $rows = [];
        foreach ($enemies as $enemy) {
            $traits = array_merge(
                $enemy['traits'] ?? [],
                StatFormula::xparamTraits((int) $enemy['stats']['dex'], (int) $enemy['stats']['agi'], (int) $enemy['stats']['luk']),
            );

            $rows[] = [
                'id' => $enemy['id'],
                'name' => $enemy['name'],
                'note' => $enemy['note'] ?: null,
                'hp' => $enemy['stats']['hp'],
                'mp' => $enemy['stats']['mp'],
                'str' => $enemy['stats']['str'],
                'vit' => $enemy['stats']['vit'],
                'mnd' => $enemy['stats']['mnd'],
                'dex' => $enemy['stats']['dex'],
                'agi' => $enemy['stats']['agi'],
                'luk' => $enemy['stats']['luk'],
                'int' => $enemy['stats']['int'],
                'reward_exp' => $enemy['rewardExp'],
                'reward_gold' => $enemy['rewardGold'],
                'drops' => json_encode($enemy['drops'] ?? []),
                'actions' => json_encode($this->convertActions($enemy['actions'] ?? [])),
                'image_type' => $enemy['imageType'],
                'image' => $enemy['image'],
                'scale' => $enemy['scale'],
                'motion_map' => json_encode($enemy['motionMap'] ?? []),
                'traits' => json_encode($traits),
            ];
        }
        DB::table('mz_enemies')->insert($rows);
        $this->command?->info('mz_enemies 임포트 완료 (' . count($rows) . '개).');

        $this->syncEnemyUnits($enemies);
    }

    /** @return array<int, array{conditionType:int,conditionParam1:float,conditionParam2:float,skillId:int,rating:int}> */
    private function convertActions(array $actions): array
    {
        return array_map(function (array $a) {
            $type = self::CONDITION_TYPE_MAP[$a['conditionType']] ?? 0;

            return [
                'conditionType' => $type,
                'conditionParam1' => $type === 4 ? $a['stateId'] : $a['param1'],
                'conditionParam2' => $a['param2'],
                'skillId' => $a['skillId'],
                'rating' => $a['priority'],
            ];
        }, $actions);
    }

    /** MzImportSeeder::syncEnemyUnits()와 동일한 upsert 패턴(과거 전투 기록의 unit_id RESTRICT 때문에 이름으로 찾음). */
    private function syncEnemyUnits(array $enemies): void
    {
        $effectNameByAnimationName = MzAnimation::pluck('effect_name', 'name')->all();
        $missingAnimationNames = [];

        foreach ($enemies as $enemy) {
            if ($enemy['name'] === '') {
                continue;
            }

            $tags = MzNoteTagParser::parseEnemyTags($enemy['note'] ?? '');
            $weaponEffectName = null;
            if ($tags['attack_animation_name'] !== null) {
                $weaponEffectName = $effectNameByAnimationName[$tags['attack_animation_name']] ?? null;
                if ($weaponEffectName === null) {
                    $missingAnimationNames[] = $tags['attack_animation_name'];
                }
            }

            $motionMap = [];
            foreach ($tags['dragonbones_motions'] as [$motionName, $clipName]) {
                $motionMap[$motionName] = $clipName;
            }
            // note의 <DragonBonesMotion> 태그가 없으면 Enemy.motionMap(RPGEditor에서
            // 직접 관리하는 SV모션<->클립 매핑)을 그대로 쓴다.
            if ($motionMap === []) {
                foreach ($enemy['motionMap'] ?? [] as $m) {
                    $motionMap[$m['motion']] = $m['animationName'];
                }
            }

            $stats = StatFormula::deriveCombatStats([
                'hp' => $enemy['stats']['hp'], 'mp' => $enemy['stats']['mp'],
                'str' => $enemy['stats']['str'], 'vit' => $enemy['stats']['vit'], 'mnd' => $enemy['stats']['mnd'],
                'dex' => $enemy['stats']['dex'], 'agi' => $enemy['stats']['agi'], 'luk' => $enemy['stats']['luk'],
                'int' => $enemy['stats']['int'],
            ]);

            Unit::updateOrCreate(
                ['type' => 'enemy', 'name' => $enemy['name']],
                [
                    'mz_enemy_id' => $enemy['id'],
                    'sprite' => 'Enemy:' . $enemy['image'],
                    'attack_motion' => 'thrust',
                    'weapon_effect_name' => $weaponEffectName,
                    'dragonbones_skeleton' => $enemy['imageType'] === 'dragonBones' ? $enemy['image'] : ($tags['dragonbones_skeleton'] ?? null),
                    'dragonbones_atlas' => $tags['dragonbones_atlas'],
                    'dragonbones_motions' => $motionMap === [] ? null : $motionMap,
                    'dragonbones_scale' => $tags['dragonbones_scale'] ?? (int) round($enemy['scale']),
                    'max_hp' => $stats['max_hp'], 'max_mp' => $stats['max_mp'],
                    'atk' => $stats['atk'], 'def' => $stats['def'], 'mat' => $stats['mat'], 'mdf' => $stats['mdf'],
                    'spd' => $stats['spd'], 'luk' => $stats['luk'],
                ],
            );
        }

        if ($missingAnimationNames !== []) {
            throw new \RuntimeException(
                '적 노트태그가 존재하지 않는 애니메이션을 참조합니다: ' . implode(', ', array_unique($missingAnimationNames)),
            );
        }
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
