<?php

namespace Database\Seeders;

use App\Support\StatFormula;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * RPGProject/data/Items.json 중 kind=weapon만 -> mz_weapons. id는 원본과 1:1 고정
 * 유지(Actors.json equips[0], CraftMaterial.itemId가 참조). params는 EquipParams
 * (9 원시 스탯 + atk/def/matk/mdef 직접 보너스)를 StatFormula::equipParamsToMzArray()로
 * MZ 8스탯 배열([max_hp,max_mp,atk,def,mat,mdf,agi,luk])로 접어서 저장 - 그래야
 * BattleEngine::equipmentStatsFor()가 지금 코드 그대로 합산할 수 있다. hit/eva/crit
 * 직접 보너스는 traits(code=22 xparam)로 변환.
 */
class WeaponSeeder extends Seeder
{
    private const DATA_PATH = __DIR__ . '/../../../RPGProject/data';

    public function run(): void
    {
        $items = $this->readJson('Items.json');

        DB::table('mz_weapons')->delete();

        $rows = [];
        foreach ($items as $item) {
            if ($item['kind'] !== 'weapon') {
                continue;
            }
            $w = $item['weapon'];
            $p = $w['params'];

            $rows[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'note' => $item['note'] ?: null,
                'wtype_id' => $w['weaponTypeId'],
                'etype_id' => 1, // MZ 표준 etype(1=무기/2=방패/3=머리/4=몸/5=장신구) - EquipmentController가 이 값 기준.
                'animation_id' => $w['animationId'],
                'icon_index' => $item['iconIndex'],
                'price' => $item['price'],
                'description' => $item['description'] ?: null,
                'params' => json_encode(StatFormula::equipParamsToMzArray($p)),
                'traits' => json_encode(array_merge($w['traits'] ?? [], StatFormula::equipXparamTraits($p))),
                'crafting_cost' => $w['craftingCost'],
                'crafting_time' => $w['craftingTime'],
                'tags' => json_encode(['crafting' => $this->craftingTag($w, $items)]),
            ];
        }
        DB::table('mz_weapons')->insert($rows);

        $this->command?->info('mz_weapons 임포트 완료 (' . count($rows) . '개).');
    }

    /**
     * CraftMaterial[]({itemId,quantity})을 CraftingController가 읽는
     * {seconds, materials:[{type,name,count}], gold_cost} 형태로 변환한다 - type은
     * 그 itemId가 Items.json에서 실제로 어느 kind인지 찾아서 결정.
     */
    private function craftingTag(array $equip, array $allItems): ?array
    {
        $materials = $equip['craftingMaterials'] ?? [];
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

        return ['seconds' => $equip['craftingTime'], 'materials' => array_values($resolved), 'gold_cost' => $equip['craftingCost']];
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
