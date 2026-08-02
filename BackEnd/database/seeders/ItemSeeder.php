<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * RPGProject/data/Items.json 중 kind=consumable|material만 -> mz_items(weapon/armor는
 * WeaponSeeder/ArmorSeeder가 따로 처리). id는 원본과 1:1 고정 유지(CraftMaterial.itemId,
 * BattleUnit/gambit 아이템 참조). scope/hitType/damageType은 SkillSeeder와 동일하게
 * MZ 표준 숫자 코드로 변환.
 */
class ItemSeeder extends Seeder
{
    private const DATA_PATH = __DIR__ . '/../../../RPGProject/data';

    private const SCOPE_MAP = ['self' => 11, 'oneEnemy' => 1, 'oneAlly' => 7, 'allEnemies' => 2, 'allAllies' => 8];

    private const HIT_TYPE_MAP = ['certain' => 0, 'physical' => 1, 'magical' => 2];

    private const DAMAGE_TYPE_MAP = [
        'none' => 0, 'hpDamage' => 1, 'mpDamage' => 2, 'hpRecover' => 3,
        'mpRecover' => 4, 'hpDrain' => 5, 'mpDrain' => 6,
    ];

    public function run(): void
    {
        $items = $this->readJson('Items.json');
        $stateNames = collect($this->readJson('States.json'))->pluck('name', 'id')->all();

        DB::table('mz_items')->delete();

        $rows = [];
        $referencedStates = [];

        foreach ($items as $item) {
            if (! in_array($item['kind'], ['consumable', 'material'], true)) {
                continue;
            }

            $c = $item['consumable'] ?? null;
            $isConsumable = $item['kind'] === 'consumable';
            $note = $item['note'] ?? '';

            [$recoverHp, $recoverMp, $addStates, $removeStates, $referenced] = $isConsumable
                ? $this->resolveEffects($c['effects'] ?? [], $stateNames)
                : [['rate' => 0.0, 'flat' => 0], ['rate' => 0.0, 'flat' => 0], [], [], []];
            array_push($referencedStates, ...$referenced);

            $tags = [];
            $tags['recover_hp'] = $recoverHp;
            $tags['recover_mp'] = $recoverMp;
            $tags['add_states'] = $addStates;
            $tags['remove_states'] = $removeStates;
            // ApplySelfState(지속 턴 커스텀 오버라이드 가능)/ExcludeSelf는 RPGEditor
            // 아이템 편집 화면의 구조화 필드(ApplyStateId+ApplyStateTurns, ExcludeSelf
            // 체크박스)로 대체됐다.
            $applyStateId = $c['applyStateId'] ?? null;
            $tags['apply_self_state'] = $applyStateId !== null
                ? [$stateNames[$applyStateId] ?? null, $c['applyStateTurns'] ?? null]
                : null;
            if (($tags['apply_self_state'][0] ?? null) !== null) {
                $referencedStates[] = $tags['apply_self_state'][0];
            }
            $tags['exclude_self'] = $c['excludeSelf'] ?? false;
            $tags['crafting'] = $isConsumable && ($c['craftingMaterials'] ?? []) !== []
                ? $this->craftingTag($c, $items)
                : null;

            $rows[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'note' => $note ?: null,
                'item_type_id' => $isConsumable ? 0 : 1,
                'consumable' => $isConsumable,
                'price' => $item['price'],
                'icon_index' => $item['iconIndex'],
                'animation_id' => $isConsumable ? $c['invocation']['animationId'] : 0,
                'scope' => $isConsumable ? (self::SCOPE_MAP[$c['scope']] ?? 7) : null,
                'occasion' => 0,
                'hit_type' => $isConsumable ? (self::HIT_TYPE_MAP[$c['invocation']['hitType']] ?? 0) : null,
                'speed' => $isConsumable ? $c['invocation']['speedCorrection'] : 0,
                'success_rate' => $isConsumable ? $c['invocation']['successRate'] : 100,
                'repeats' => $isConsumable ? $c['invocation']['repeatCount'] : 1,
                'damage_type' => $isConsumable ? (self::DAMAGE_TYPE_MAP[$c['damage']['type']] ?? 0) : null,
                'damage_formula' => $isConsumable ? $c['damage']['formula'] : null,
                'variance' => $isConsumable ? $c['damage']['variance'] : 20,
                'element_id' => $isConsumable ? $c['damage']['elementId'] : 0,
                'description' => $item['description'] ?: null,
                'effects' => json_encode($isConsumable ? ($c['effects'] ?? []) : []),
                'crafting_cost' => $isConsumable ? ($c['craftingCost'] ?? 0) : 0,
                'crafting_time' => $isConsumable ? ($c['craftingTime'] ?? 0) : 0,
                'tags' => json_encode($tags),
            ];
        }

        DB::table('mz_items')->insert($rows);

        $missing = array_diff(array_unique($referencedStates), array_values($stateNames));
        if ($missing !== []) {
            throw new \RuntimeException('아이템 사용효과가 존재하지 않는 상태를 참조합니다: ' . implode(', ', $missing));
        }

        $this->command?->info('mz_items 임포트 완료 (' . count($rows) . '개).');
    }

    /** @return array{0: array, 1: array, 2: array, 3: array, 4: array<int,string>} */
    private function resolveEffects(array $effects, array $stateNames): array
    {
        $recoverHp = ['rate' => 0.0, 'flat' => 0];
        $recoverMp = ['rate' => 0.0, 'flat' => 0];
        $addStates = [];
        $removeStates = [];
        $referenced = [];

        foreach ($effects as $effect) {
            $stateName = $stateNames[$effect['stateId']] ?? null;
            match ($effect['kind']) {
                'targetRecoverHp' => $recoverHp = ['rate' => $effect['percentValue'] / 100, 'flat' => $effect['flatValue']],
                'targetRecoverMp' => $recoverMp = ['rate' => $effect['percentValue'] / 100, 'flat' => $effect['flatValue']],
                'targetAddState' => $stateName !== null
                    ? ($addStates[] = ['state' => $stateName, 'chance' => $effect['chance']]) && ($referenced[] = $stateName)
                    : null,
                'targetRemoveState' => $stateName !== null
                    ? ($removeStates[] = ['state' => $stateName, 'chance' => $effect['chance']]) && ($referenced[] = $stateName)
                    : null,
                default => null,
            };
        }

        return [$recoverHp, $recoverMp, $addStates, $removeStates, $referenced];
    }

    private function craftingTag(array $consumable, array $allItems): ?array
    {
        $materials = $consumable['craftingMaterials'] ?? [];
        if ($materials === []) {
            return null;
        }

        $byId = collect($allItems)->keyBy('id');
        $resolved = [];
        foreach ($materials as $m) {
            $source = $byId->get($m['itemId']);
            if ($source === null) {
                continue;
            }
            $type = match ($source['kind']) {
                'weapon' => 'weapon', 'armor' => 'armor', default => 'item',
            };
            $key = "{$type}:{$source['name']}";
            $resolved[$key] ??= ['type' => $type, 'name' => $source['name'], 'count' => 0];
            $resolved[$key]['count'] += $m['quantity'];
        }

        return ['seconds' => $consumable['craftingTime'] ?? 0, 'materials' => array_values($resolved), 'gold_cost' => $consumable['craftingCost'] ?? 0];
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
