<?php

namespace Database\Seeders;

use App\Support\MzNoteTagParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * mz_project/data/Classes.json -> mz_classes. id는 원본과 1:1 고정 유지(Skills.json
 * learnings, Actors.json classId가 숫자로 참조하기 때문). params(레벨별 스탯 곡선)까지
 * 원본 그대로 저장해서 ActorSeeder가 액터 레벨 시점 스탯을 뽑아 쓸 수 있게 한다.
 * 재실행해도 안전하게 매번 전체를 지우고 다시 채운다.
 */
class ClassSeeder extends Seeder
{
    private const MZ_DATA_PATH = __DIR__ . '/../../../mz_project/data';

    public function run(): void
    {
        $classes = $this->readJson('Classes.json');

        DB::table('mz_classes')->delete();

        $rows = [];
        foreach ($classes as $class) {
            if ($class === null) {
                continue;
            }
            $note = $class['note'] ?: '';
            $tags = MzNoteTagParser::parseClassTags($note);
            $rows[] = [
                'id' => $class['id'],
                'name' => $class['name'],
                'note' => $class['note'] ?: null,
                'params' => json_encode($class['params']),
                'traits' => json_encode($class['traits'] ?? []),
                'party_hud_icon' => $tags['party_hud_icon'],
            ];
        }
        DB::table('mz_classes')->insert($rows);

        $this->command?->info('mz_classes 임포트 완료 (' . count($rows) . '개).');
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
