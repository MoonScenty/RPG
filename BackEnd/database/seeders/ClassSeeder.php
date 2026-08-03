<?php

namespace Database\Seeders;

use App\Support\StatFormula;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * RPGProject/data/Classes.json -> mz_classes. id는 원본과 1:1 고정 유지(Skills.json
 * learnings 역산, Actors.json classId가 참조). paramCurve(레벨별 STR/VIT/MND/DEX/
 * AGI/LUK/INT+HP/MP)를 그대로 json으로 보관하고, 레벨 1 시점 스탯으로 HIT/EVA/CRI 등
 * xparam 트레잇을 합성해 traits에 얹는다(StatFormula::xparamTraits() 참고 - 이
 * 게임엔 레벨업이 없어 initialLevel이 사실상 고정 레벨이지만, 직업 하나를 여러
 * 액터가 공유할 수 있어 여기선 레벨 1 기준으로 통일했다 - 감쇠형 공식이라 레벨별
 * 오차는 크지 않다).
 */
class ClassSeeder extends Seeder
{
    private const DATA_PATH = __DIR__ . '/../../../RPGProject/data';

    public function run(): void
    {
        $classes = $this->readJson('Classes.json');

        DB::table('mz_classes')->delete();

        $rows = [];
        foreach ($classes as $class) {
            $note = $class['note'] ?? '';
            $lv1 = collect($class['paramCurve'])->firstWhere('level', 1) ?? $class['paramCurve'][0];

            $traits = array_merge(
                $class['traits'] ?? [],
                StatFormula::xparamTraits((int) $lv1['dex'], (int) $lv1['agi'], (int) $lv1['luk'], (int) $lv1['mnd']),
            );

            $rows[] = [
                'id' => $class['id'],
                'name' => $class['name'],
                'note' => $note ?: null,
                'icon_index' => $class['iconIndex'],
                'exp_curve' => json_encode($class['expCurve']),
                'param_curve' => json_encode($class['paramCurve']),
                'traits' => json_encode($traits),
            ];
        }
        DB::table('mz_classes')->insert($rows);

        $this->command?->info('mz_classes 임포트 완료 (' . count($rows) . '개).');
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
