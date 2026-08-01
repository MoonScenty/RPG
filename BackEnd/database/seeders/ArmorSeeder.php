<?php

namespace Database\Seeders;

use App\Support\StatFormula;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * RPGProject/data/Items.json 중 kind=armor만 -> mz_armors. WeaponSeeder와 동일한
 * 패턴. etype_id는 MZ 표준(1=무기/2=방패/3=머리/4=몸/5=장신구) - 우리 EquipTypeId
 * (0=무기/1=방패/2=머리/3=몸/4=장신구, Types.json equipTypes 배열 순서)에 +1.
 */
class ArmorSeeder extends Seeder
{
    private const DATA_PATH = __DIR__ . '/../../../RPGProject/data';

    public function run(): void
    {
        $items = $this->readJson('Items.json');

        DB::table('mz_armors')->delete();

        $rows = [];
        foreach ($items as $item) {
            if ($item['kind'] !== 'armor') {
                continue;
            }
            $a = $item['armor'];
            $p = $a['params'];

            $rows[] = [
                'id' => $item['id'],
                'name' => $item['name'],
                'note' => $item['note'] ?: null,
                'atype_id' => $a['armorTypeId'],
                'etype_id' => $a['equipTypeId'] + 1,
                'icon_index' => $item['iconIndex'],
                'price' => $item['price'],
                'description' => $item['description'] ?: null,
                'params' => json_encode(StatFormula::equipParamsToMzArray($p)),
                'traits' => json_encode(array_merge($a['traits'] ?? [], StatFormula::equipXparamTraits($p))),
                // Items.json의 방어구 335개 전체가 아직 craftingCost/craftingTime/
                // craftingMaterials 필드 자체가 없다(RPGEditor 스키마에 나중에 추가돼서).
                'crafting_cost' => $a['craftingCost'] ?? 0,
                'crafting_time' => $a['craftingTime'] ?? 0,
                'tags' => json_encode(['crafting' => $this->craftingTag($a, $items)]),
            ];
        }
        DB::table('mz_armors')->insert($rows);

        $this->command?->info('mz_armors 임포트 완료 (' . count($rows) . '개).');
    }

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

        return ['seconds' => $equip['craftingTime'] ?? 0, 'materials' => array_values($resolved), 'gold_cost' => $equip['craftingCost'] ?? 0];
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
