<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * RPGProject/data/States.json -> mz_states. id는 원본과 1:1 고정 유지(스킬 노트태그/
 * effects가 이름 또는 id로 참조). motion/autoRemovalTiming은 우리 문자열 enum을
 * BattleEngine이 전제하는 MZ 표준 숫자 코드로 변환.
 */
class StateSeeder extends Seeder
{
    private const DATA_PATH = __DIR__ . '/../../../RPGProject/data';

    private const MOTION_MAP = ['' => 0, 'abnormal' => 1, 'sleep' => 2, 'dead' => 3];

    private const AUTO_REMOVAL_MAP = ['none' => 0, 'actionEnd' => 1, 'turnEnd' => 2];

    public function run(): void
    {
        $states = $this->readJson('States.json');

        DB::table('mz_states')->delete();

        $rows = [];
        foreach ($states as $state) {
            $rows[] = [
                'id' => $state['id'],
                'name' => $state['name'],
                'note' => $state['note'] ?: null,
                'icon_index' => $state['iconIndex'],
                'priority' => $state['priority'],
                'motion' => self::MOTION_MAP[$state['motion']] ?? 0,
                'remove_at_battle_end' => $state['removeAtBattleEnd'],
                'remove_by_restriction' => $state['removeByRestriction'],
                'auto_removal_timing' => self::AUTO_REMOVAL_MAP[$state['autoRemovalTiming']] ?? 0,
                'min_turns' => $state['minTurns'],
                'max_turns' => $state['maxTurns'],
                'remove_by_damage' => $state['removeByDamage'],
                'remove_by_damage_chance' => $state['removeByDamageChance'],
                'message_when_added' => $state['messageWhenAdded'] ?: null,
                'message_when_added_enemy' => $state['messageWhenAddedEnemy'] ?: null,
                'message_while_active' => $state['messageWhileActive'] ?: null,
                'message_when_removed' => $state['messageWhenRemoved'] ?: null,
                'traits' => json_encode($state['traits'] ?? []),
                'is_debuff' => $state['isDebuff'] ?? false,
                'tags' => json_encode([
                    'dot_percent' => $state['dotPercent'] ?? null,
                    'hot_percent' => $state['hotPercent'] ?? null,
                    'taunt' => $state['taunt'] ?? false,
                    'guard_ally' => $state['guardAlly'] ?? false,
                    'damage_taken_rate' => $state['damageTakenRate'] ?? null,
                    'shield_pct' => $state['shieldAbsorbPercent'] ?? null,
                    'undying' => $state['undying'] ?? false,
                ]),
            ];
        }
        DB::table('mz_states')->insert($rows);

        $this->command?->info('mz_states 임포트 완료 (' . count($rows) . '개).');
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
