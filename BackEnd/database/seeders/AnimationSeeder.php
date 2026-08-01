<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** RPGProject/data/Animations.json -> mz_animations. id는 원본과 1:1 고정 유지(Skills.json animationId가 참조). */
class AnimationSeeder extends Seeder
{
    private const DATA_PATH = __DIR__ . '/../../../RPGProject/data';

    private const DISPLAY_TYPE_MAP = ['onEachTarget' => 0, 'centerOfTargets' => 1, 'centerOfScreen' => 2];

    public function run(): void
    {
        $animations = $this->readJson('Animations.json');

        DB::table('mz_animations')->delete();

        $rows = [];
        foreach ($animations as $animation) {
            $rows[] = [
                'id' => $animation['id'],
                'name' => $animation['name'],
                'note' => $animation['note'] ?? null,
                'display_type' => self::DISPLAY_TYPE_MAP[$animation['displayType']] ?? 0,
                'effect_name' => $animation['effectName'] ?: null,
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
        $path = self::DATA_PATH . '/' . $file;
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("RPGProject 데이터를 읽지 못했습니다: {$path}");
        }

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
