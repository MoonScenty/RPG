<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * RPGProject/data/Troops.json -> mz_troops. id는 원본과 1:1 고정 유지
 * (BattleEngine::spawnEnemyTroop()이 트룹 1번을 고정 참조). members[] pivot 대신
 * 6슬롯(frontTop~backBottom)을 mz_enemies.id를 직접 가리키는 컬럼으로 저장.
 */
class TroopSeeder extends Seeder
{
    private const DATA_PATH = __DIR__ . '/../../../RPGProject/data';

    private const SLOTS = [
        'frontTopEnemyId' => 'front_top_enemy_id', 'frontMiddleEnemyId' => 'front_middle_enemy_id',
        'frontBottomEnemyId' => 'front_bottom_enemy_id', 'backTopEnemyId' => 'back_top_enemy_id',
        'backMiddleEnemyId' => 'back_middle_enemy_id', 'backBottomEnemyId' => 'back_bottom_enemy_id',
    ];

    public function run(): void
    {
        $troops = $this->readJson('Troops.json');

        DB::table('mz_troops')->delete();

        $rows = [];
        foreach ($troops as $troop) {
            $row = [
                'id' => $troop['id'],
                'name' => $troop['name'] ?: "트룹 {$troop['id']}",
                'battleback1' => $troop['battleback1'] ?: null,
                'battleback2' => $troop['battleback2'] ?: null,
                'battle_bgm' => json_encode($troop['battleBgm']),
                'victory_me' => json_encode($troop['victoryMe']),
                'defeat_me' => json_encode($troop['defeatMe']),
            ];
            foreach (self::SLOTS as $jsonKey => $column) {
                $row[$column] = $troop[$jsonKey] ?? null;
            }
            $rows[] = $row;
        }
        DB::table('mz_troops')->insert($rows);

        $this->command?->info('mz_troops 임포트 완료 (' . count($rows) . '개).');
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
