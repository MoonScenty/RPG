<?php

namespace Database\Seeders;

use App\Support\MzNoteTagParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * mz_project/data/Weapons.json -> mz_weapons. id는 원본과 1:1 고정 유지(Actors.json
 * equips[0]이 참조). 이름이 빈 슬롯(MZ 기본 템플릿이 미리 깔아두는 빈 자리)은
 * 스킵 - Enemies.json 임포트와 동일한 컨벤션. 아직 장비 시스템 자체가 없어서
 * BattleEngine/units가 이 테이블을 참조하지는 않고, 데이터만 원본 그대로 보관한다.
 * note의 <Crafting> 태그만 미리 해석해 tags에 저장(CraftingController가 읽음).
 * 재실행해도 안전하게 매번 전체를 지우고 다시 채운다.
 */
class WeaponSeeder extends Seeder
{
    private const MZ_DATA_PATH = __DIR__ . '/../../../mz_project/data';

    public function run(): void
    {
        $weapons = $this->readJson('Weapons.json');

        DB::table('mz_weapons')->delete();

        $rows = [];
        foreach ($weapons as $weapon) {
            if ($weapon === null || $weapon['name'] === '') {
                continue;
            }
            $rows[] = [
                'id' => $weapon['id'],
                'name' => $weapon['name'],
                'wtype_id' => $weapon['wtypeId'],
                'etype_id' => $weapon['etypeId'],
                'animation_id' => $weapon['animationId'],
                'icon_index' => $weapon['iconIndex'],
                'price' => $weapon['price'],
                'description' => $weapon['description'] ?: null,
                'note' => $weapon['note'] ?: null,
                'params' => json_encode($weapon['params']),
                'traits' => json_encode($weapon['traits'] ?? []),
                'tags' => json_encode(['crafting' => MzNoteTagParser::parseCraftingTag($weapon['note'] ?? '')]),
            ];
        }
        DB::table('mz_weapons')->insert($rows);

        $this->command?->info('mz_weapons 임포트 완료 (' . count($rows) . '개).');
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
