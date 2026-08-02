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
            // <RequireFront>/<RequireBack>/<SkillMotion>은 usablePosition/motion 구조화
            // 필드가 생기면서 지웠다(RPGEditor 스킬 편집 화면에 이미 UI가 있음) - 이제
            // 이 두 필드가 유일한 기준이다.
            $tags['require_position'] = $skill['usablePosition'] === 'any' ? null : $skill['usablePosition'];
            $tags['skill_motion'] = $skill['motion'] ?: null;
            // RPGEditor AnimationField(무기 쪽과 별개 컨트롤)는 0도 목록에 있는 진짜
            // 애니메이션("0: 타격/물리적")이라 선택 가능하게 다룬다 - "없음"은 매칭되는
            // 항목이 아예 없을 때만 표시되지, 0을 "미설정"으로 취급하는 별도 규칙이
            // 없다(무기의 animation_id > 0 체크와 착각해서 여기도 0을 걸러냈다가
            // 사용자가 실제로 골라둔 0을 지워버린 사고가 있었음 - 그대로 저장한다).
            $tags['skill_animation_id'] = $skill['invocation']['animationId'] ?? null;

            $resolved = $this->resolveEffects($skill['effects'] ?? [], $stateNames);
            $tags['target_add_states'] = $resolved['target_add_states'];
            $tags['target_remove_states'] = $resolved['target_remove_states'];
            $tags['user_add_states'] = $resolved['user_add_states'];
            $tags['user_remove_states'] = $resolved['user_remove_states'];
            $tags['target_recover_hp'] = $resolved['target_recover_hp'];
            $tags['target_recover_mp'] = $resolved['target_recover_mp'];
            $tags['user_recover_hp'] = $resolved['user_recover_hp'];
            $tags['user_recover_mp'] = $resolved['user_recover_mp'];
            $tags['atb_boost_effect'] = $resolved['atb_boost'];
            array_push($referencedStates, ...$resolved['referenced']);

            // "요구 상태"/"사용 시 요구 상태 해제"/"마법진 이미지"는 RPGEditor 스킬
            // 편집 화면에 이미 구조화 UI(StateField 피커/체크박스/텍스트박스)가
            // 있었는데 여기서 안 읽어서 완전히 죽어있던 필드였다(실제로는 노트태그
            // RequireSelfState/CircleImage가 대신 쓰이고 있었음, CircleImage는
            // pairValue()가 0-인덱스 배열을 주는데 BattleEngine::log()는 ['name']/
            // ['scale'] 키로 읽어서 그마저도 항상 null이었던 별개의 버그까지 있었다).
            // 이제 구조화 필드가 유일한 소스 - require_self_state/circle_image는
            // 아래에서 note 파싱 결과를 덮어쓴다.
            $tags['require_self_state'] = $skill['requiredStateId'] !== null
                ? ($stateNames[$skill['requiredStateId']] ?? null)
                : null;
            $tags['remove_required_state_on_use'] = $skill['removeRequiredStateOnUse'] ?? false;
            $magicCircleImage = $skill['magicCircleImage'] ?? '';
            $tags['circle_image'] = $magicCircleImage !== ''
                ? ['name' => $magicCircleImage, 'scale' => $skill['magicCircleScale'] ?? 1]
                : null;

            // 캐스팅/쿨다운/MP 추가 회복/흡혈/게이지 증가량/전MP 소모/자리 변경도
            // 전부 RPGEditor 스킬 편집 화면에 구조화 필드(TextBox/CheckBox)가 새로
            // 생겨서, 이제 그 필드가 유일한 소스다(Casting/Cooldown/MpRecover/
            // Lifesteal/GaugeBoost/ConsumeAllMp/SwapPosition 노트태그는 파서에서 제거).
            $tags['casting_turns'] = $skill['castingTurns'] ?? null;
            $tags['cooldown_turns'] = $skill['cooldownTurns'] ?? null;
            $tags['mp_recover'] = $skill['mpRecoverOnUse'] ?? null;
            $tags['lifesteal_pct'] = $skill['lifestealPercent'] ?? null;
            $tags['gauge_boost'] = $skill['gaugeBoostAmount'] ?? null;
            $tags['consume_all_mp'] = $skill['consumeAllMp'] ?? false;
            $tags['swap_position'] = $skill['swapPosition'] ?? false;

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
            if ($skill['targetDeadAllies'] ?? false) {
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
     * 우리 SkillEffect[]({kind,percentValue,flatValue,stateId,chance})를 종류별로
     * 분류해 tags 형태로 변환한다(BattleEngine이 읽는 형태) - Add/RemoveState는
     * {state,chance}, RecoverHp/RecoverMp는 {percent,flat,chance}(대상 최대치 대비
     * 퍼센트 + 고정치, SkillEffect 모델 주석과 동일한 규칙), AtbBoost는 flatValue를
     * 그대로 게이지 증가량으로 쓴다(여러 줄이면 합산).
     *
     * 9개 kind 중 TargetAddState/TargetRemoveState 2개만 처리되고 나머지 7개
     * (User*, *RecoverHp, *RecoverMp, AtbBoost)는 무시되던 게 예전 상태였다 - 이미
     * Skills.json에 이 종류로 저장된 스킬(예: 8/13/23 "userAddState", 29 "atbBoost")
     * 이 실제로 존재해서, 그 스킬들은 지금까지 게임에서 조용히 아무 효과도 없었다.
     *
     * @return array{
     *     target_add_states: array<int, array{state: string, chance: int}>,
     *     target_remove_states: array<int, array{state: string, chance: int}>,
     *     user_add_states: array<int, array{state: string, chance: int}>,
     *     user_remove_states: array<int, array{state: string, chance: int}>,
     *     target_recover_hp: array<int, array{percent: int, flat: int, chance: int}>,
     *     target_recover_mp: array<int, array{percent: int, flat: int, chance: int}>,
     *     user_recover_hp: array<int, array{percent: int, flat: int, chance: int}>,
     *     user_recover_mp: array<int, array{percent: int, flat: int, chance: int}>,
     *     atb_boost: int|null,
     *     referenced: array<int, string>,
     * }
     */
    private function resolveEffects(array $effects, array $stateNames): array
    {
        $result = [
            'target_add_states' => [], 'target_remove_states' => [],
            'user_add_states' => [], 'user_remove_states' => [],
            'target_recover_hp' => [], 'target_recover_mp' => [],
            'user_recover_hp' => [], 'user_recover_mp' => [],
            'atb_boost' => null,
            'referenced' => [],
        ];

        foreach ($effects as $effect) {
            $stateName = $stateNames[$effect['stateId']] ?? null;
            $chance = $effect['chance'];
            $recover = ['percent' => $effect['percentValue'], 'flat' => $effect['flatValue'], 'chance' => $chance];

            switch ($effect['kind']) {
                case 'targetAddState':
                    if ($stateName !== null) {
                        $result['target_add_states'][] = ['state' => $stateName, 'chance' => $chance];
                        $result['referenced'][] = $stateName;
                    }
                    break;
                case 'targetRemoveState':
                    if ($stateName !== null) {
                        $result['target_remove_states'][] = ['state' => $stateName, 'chance' => $chance];
                        $result['referenced'][] = $stateName;
                    }
                    break;
                case 'userAddState':
                    if ($stateName !== null) {
                        $result['user_add_states'][] = ['state' => $stateName, 'chance' => $chance];
                        $result['referenced'][] = $stateName;
                    }
                    break;
                case 'userRemoveState':
                    if ($stateName !== null) {
                        $result['user_remove_states'][] = ['state' => $stateName, 'chance' => $chance];
                        $result['referenced'][] = $stateName;
                    }
                    break;
                case 'targetRecoverHp':
                    $result['target_recover_hp'][] = $recover;
                    break;
                case 'targetRecoverMp':
                    $result['target_recover_mp'][] = $recover;
                    break;
                case 'userRecoverHp':
                    $result['user_recover_hp'][] = $recover;
                    break;
                case 'userRecoverMp':
                    $result['user_recover_mp'][] = $recover;
                    break;
                case 'atbBoost':
                    $result['atb_boost'] = ($result['atb_boost'] ?? 0) + $effect['flatValue'];
                    break;
            }
        }

        return $result;
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
