<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * RPGProject/data/Animations_mv.json(RPG Maker MV 원본 포맷, 배열 index 0은 항상
 * null) -> mz_animations. id는 원본과 1:1 고정 유지(Items.json weapon.animationId,
 * Enemies.json note의 <AttackAnimation: id>가 참조). name은 원본 파일 자체가 인코딩
 * 깨짐 상태로 와서(복구 불가) 그대로 저장만 하고 참조 용도로 쓰지 않는다.
 */
class AnimationSeeder extends Seeder
{
    private const DATA_PATH = __DIR__ . '/../../../RPGProject/data';

    public function run(): void
    {
        $animations = $this->readJson('Animations_mv.json');

        DB::table('mz_animations')->delete();

        $rows = [];
        foreach ($animations as $animation) {
            if ($animation === null) {
                continue;
            }
            $rows[] = [
                'id' => $animation['id'],
                'name' => $animation['name'] ?: null,
                'animation1_name' => $animation['animation1Name'] ?: null,
                'animation1_hue' => $animation['animation1Hue'] ?? 0,
                'animation2_name' => $animation['animation2Name'] ?: null,
                'animation2_hue' => $animation['animation2Hue'] ?? 0,
                'position' => $animation['position'] ?? 1,
                'frames' => json_encode($animation['frames'] ?? []),
                'timings' => json_encode($animation['timings'] ?? []),
            ];
        }
        DB::table('mz_animations')->insert($rows);

        $this->command?->info('mz_animations 임포트 완료 (' . count($rows) . '개).');
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
