<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * mz_project/data/Animations.json -> mz_animations. id는 원본과 1:1 고정 유지
 * (Skills.json의 animationId가 참조). 전부 이름이 채워진 MZ 기본 이펙트 라이브러리라
 * Weapons.json과 달리 빈 슬롯 스킵 로직은 없음(null만 스킵). 아직 PixiJS 쪽에
 * Effekseer 이펙트 재생기가 없어서 데이터만 원본 그대로 보관한다.
 * 재실행해도 안전하게 매번 전체를 지우고 다시 채운다.
 */
class AnimationSeeder extends Seeder
{
    private const MZ_DATA_PATH = __DIR__ . '/../../../mz_project/data';

    public function run(): void
    {
        $animations = $this->readJson('Animations.json');

        DB::table('mz_animations')->delete();

        $rows = [];
        foreach ($animations as $animation) {
            if ($animation === null) {
                continue;
            }
            $rows[] = [
                'id' => $animation['id'],
                'name' => $animation['name'],
                'effect_name' => $animation['effectName'] ?: null,
                'display_type' => $animation['displayType'],
                'offset_x' => $animation['offsetX'],
                'offset_y' => $animation['offsetY'],
                'scale' => $animation['scale'],
                'speed' => $animation['speed'],
                'rotation' => json_encode($animation['rotation'] ?? []),
                'flash_timings' => json_encode($animation['flashTimings'] ?? []),
                'sound_timings' => json_encode($animation['soundTimings'] ?? []),
            ];
        }
        DB::table('mz_animations')->insert($rows);

        $this->command?->info('mz_animations 임포트 완료 (' . count($rows) . '개).');
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
