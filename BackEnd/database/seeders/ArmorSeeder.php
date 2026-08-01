<?php

namespace Database\Seeders;

use App\Support\MzNoteTagParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * mz_project/data/Armors.json -> mz_armors. id는 원본과 1:1 고정 유지(Actors.json
 * equips[1..4]가 참조). 이름이 빈 슬롯이거나 "-----"로 시작하는 에디터 구분선
 * 더미는 스킵(WeaponSeeder/Skills 임포트와 동일 컨벤션). 아직 장비 시스템 자체가
 * 없어서 BattleEngine/units가 이 테이블을 참조하지는 않고, 데이터만 원본 그대로
 * 보관한다. note의 <Crafting> 태그만 미리 해석해 tags에 저장(CraftingController가
 * 읽음). 재실행해도 안전하게 매번 전체를 지우고 다시 채운다.
 */
class ArmorSeeder extends Seeder
{
    private const MZ_DATA_PATH = __DIR__ . '/../../../mz_project/data';

    public function run(): void
    {
        $armors = $this->readJson('Armors.json');

        DB::table('mz_armors')->delete();

        $rows = [];
        foreach ($armors as $armor) {
            if ($armor === null || $armor['name'] === '' || str_starts_with($armor['name'], '-----')) {
                continue;
            }
            $rows[] = [
                'id' => $armor['id'],
                'name' => $armor['name'],
                'atype_id' => $armor['atypeId'],
                'etype_id' => $armor['etypeId'],
                'icon_index' => $armor['iconIndex'],
                'price' => $armor['price'],
                'description' => $armor['description'] ?: null,
                'note' => $armor['note'] ?: null,
                'params' => json_encode($armor['params']),
                'traits' => json_encode($armor['traits'] ?? []),
                'tags' => json_encode(['crafting' => MzNoteTagParser::parseCraftingTag($armor['note'] ?? '')]),
            ];
        }
        DB::table('mz_armors')->insert($rows);

        $this->command?->info('mz_armors 임포트 완료 (' . count($rows) . '개).');
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
