<?php

namespace Database\Seeders;

use App\Support\MzNoteTagParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * RPGProject/data/Skills.json -> mz_skills. id는 원본과 1:1 고정 유지(Actors.json
 * classId를 통한 learnings 역산, battle_units.casting_skill_id, mz_enemies.actions가
 * 숫자로 참조). "-----이름" 더미 구분자는 스킵.
 *
 * scope/hitType/damageType은 RPGEditor가 쓰는 문자열 enum을 BattleEngine이 이미
 * 전제하는 MZ 표준 숫자 코드로 변환해서 저장한다(엔진 코드를 손대지 않기 위함).
 * class_id/learn_level은 Classes.json의 learnings[]에서 역산 - 더미가 아닌 스킬
 * 중 어느 직업의 learnings에도 없으면 공용 스킬(class_id=null, 예: 공격/자리 이동).
 */
class SkillSeeder extends Seeder
{
    private const DATA_PATH = __DIR__ . '/../../../RPGProject/data';

    private const SCOPE_MAP = [
        'oneEnemy' => 1, 'allEnemies' => 2, 'oneAlly' => 7, 'allAllies' => 8, 'user' => 11,
        'enemyFrontRow' => 2, 'enemyBackRow' => 2, 'allyFrontRow' => 8, 'allyBackRow' => 8,
    ];

    private const ROW_TAG_MAP = [
        'enemyFrontRow' => 'TargetFrontRow', 'enemyBackRow' => 'TargetBackRow',
        'allyFrontRow' => 'TargetFrontRow', 'allyBackRow' => 'TargetBackRow',
    ];

    private const HIT_TYPE_MAP = ['certain' => 0, 'physical' => 1, 'magical' => 2];

    private const DAMAGE_TYPE_MAP = [
        'none' => 0, 'hpDamage' => 1, 'mpDamage' => 2, 'hpRecover' => 3,
        'mpRecover' => 4, 'hpDrain' => 5, 'mpDrain' => 6,
    ];

    public function run(): void
    {
        $skills = $this->readJson('Skills.json');
        $classes = $this->readJson('Classes.json');
        $states = $this->readJson('States.json');

        $stateNames = collect($states)->pluck('name', 'id')->all();

        // skillId -> [classId, level] (더미/공용 스킬은 어느 learnings에도 없음).
        $learning = [];
        foreach ($classes as $class) {
            foreach ($class['learnings'] ?? [] as $l) {
                $learning[$l['skillId']] = ['class_id' => $class['id'], 'level' => $l['level']];
            }
        }

        DB::table('mz_skills')->delete();

        $rows = [];
        $referencedStates = [];

        foreach ($skills as $skill) {
            if (str_starts_with($skill['name'], '-----')) {
                continue;
            }

            $note = $skill['note'] ?? '';
            $tags = MzNoteTagParser::parseSkillTags($note);
            [$targetAdd, $targetRemove, $referenced] = $this->resolveEffects($skill['effects'] ?? [], $stateNames);
            $tags['target_add_states'] = $targetAdd;
            $tags['target_remove_states'] = $targetRemove;
            array_push($referencedStates, ...$referenced);

            foreach (['require_target_state', 'require_self_state', 'apply_self_state'] as $key) {
                if ($tags[$key] !== null) {
                    $referencedStates[] = $tags[$key];
                }
            }
            foreach (['apply_target_if_has', 'apply_self_if_has'] as $key) {
                if ($tags[$key] !== null) {
                    array_push($referencedStates, ...$tags[$key]);
                }
            }

            $rawScope = $skill['scope'];
            $scope = self::SCOPE_MAP[$rawScope] ?? 1;
            if (str_contains($note, '<TargetDead>')) {
                $scope = $scope === 8 ? 10 : 9; // allAllies+TargetDead=10, oneAlly+TargetDead=9
            }
            if (isset(self::ROW_TAG_MAP[$rawScope]) && ! str_contains($note, '<' . self::ROW_TAG_MAP[$rawScope] . '>')) {
                $tags[$rawScope === 'enemyFrontRow' || $rawScope === 'allyFrontRow' ? 'target_front_row' : 'target_back_row'] = true;
            }

            $rows[] = [
                'id' => $skill['id'],
                'class_id' => $learning[$skill['id']]['class_id'] ?? null,
                'learn_level' => $learning[$skill['id']]['level'] ?? null,
                'name' => $skill['name'],
                'note' => $note ?: null,
                'icon_index' => $skill['iconIndex'],
                'description' => $skill['description'] ?: null,
                'mp_cost' => $skill['mpCost'],
                'scope' => $scope,
                'hit_type' => self::HIT_TYPE_MAP[$skill['invocation']['hitType']] ?? 1,
                'stype_id' => $skill['skillTypeId'] + 1, // 0=봉인 대상 아님 sentinel 보존, 우리 0-based를 1-based로.
                'occasion' => 0,
                'damage_type' => self::DAMAGE_TYPE_MAP[$skill['damage']['type']] ?? 0,
                'critical' => $skill['damage']['criticalEnabled'],
                'damage_formula' => $skill['damage']['formula'],
                'variance' => $skill['damage']['variance'],
                'element_id' => $skill['damage']['elementId'],
                'repeats' => $skill['invocation']['repeatCount'],
                'success_rate' => $skill['invocation']['successRate'],
                'effects' => json_encode($skill['effects'] ?? []),
                'tags' => json_encode($tags),
            ];
        }

        DB::table('mz_skills')->insert($rows);

        $missing = array_diff(array_unique($referencedStates), array_values($stateNames));
        if ($missing !== []) {
            throw new \RuntimeException('스킬 노트태그/사용효과가 존재하지 않는 상태를 참조합니다: ' . implode(', ', $missing));
        }

        $this->command?->info('mz_skills 임포트 완료 (' . count($rows) . '개).');
    }

    /**
     * 우리 SkillEffect[]({kind,percentValue,flatValue,stateId,chance})에서 target
     * Add/RemoveState만 뽑아 tags 형태({state,chance})로 변환한다(BattleEngine이
     * 읽는 형태). User* / *RecoverHp / *RecoverMp / AtbBoost 종류는 아직
     * BattleEngine에 처리 로직이 없어(예전부터 구현 안 됨) 지금은 무시 - 별도
     * 후속 작업으로 BattleEngine에 처리를 추가해야 한다.
     *
     * @return array{0: array, 1: array, 2: array<int,string>}
     */
    private function resolveEffects(array $effects, array $stateNames): array
    {
        $add = [];
        $remove = [];
        $referenced = [];

        foreach ($effects as $effect) {
            $stateName = $stateNames[$effect['stateId']] ?? null;
            if ($effect['kind'] === 'targetAddState' && $stateName !== null) {
                $add[] = ['state' => $stateName, 'chance' => $effect['chance']];
                $referenced[] = $stateName;
            } elseif ($effect['kind'] === 'targetRemoveState' && $stateName !== null) {
                $remove[] = ['state' => $stateName, 'chance' => $effect['chance']];
                $referenced[] = $stateName;
            }
        }

        return [$add, $remove, $referenced];
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
