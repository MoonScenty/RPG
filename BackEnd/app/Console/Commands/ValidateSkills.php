<?php

namespace App\Console\Commands;

use App\Support\MzFormulaEvaluator;
use Illuminate\Console\Command;

/**
 * RPGProject/data/{Skills,States,Classes,Types}.json을 DB를 거치지 않고 직접 읽어서
 * (재시딩 없이 바로 실행 가능) 데이터 정합성을 정적으로 훑는다 - 실제 전투 로직
 * (타겟팅/치명타/ATB 등)은 검증 대상이 아니다.
 *
 * 노트 태그 시스템은 이제 거의 전부 걷어냈고(MzNoteTagParser에 남은 건 SkillSeeder가
 * 자동 계산하는 TargetFrontRow/TargetBackRow뿐), Skill.cs의 구조화 필드가 유일한
 * 데이터 소스다. 그래서 이 커맨드가 잡아야 할 문제도 "노트 태그 오타"가 아니라
 * ①enum 문자열 오타(SkillSeeder가 조용히 기본값으로 폴백), ②존재하지 않는 상태 id
 * 참조, ③짝을 이뤄야 하는 필드 중 하나만 채워져서 조용히 무시되는 경우, ④BattleEngine이
 * 아예 처리하지 않는 damage.type 조합 같은 "말없이 죽어있는 데이터"로 바뀌었다.
 * SkillSeeder는 상태 참조 누락 시 예외를 던지고 멈추지만(migrate:fresh --seed 자체가
 * 실패), 이 커맨드는 끝까지 돌면서 발견한 문제를 전부 모아 표로 보여준다.
 */
class ValidateSkills extends Command
{
    protected $signature = 'skills:validate';

    protected $description = 'RPGProject/data의 Skills.json/States.json/Classes.json 정합성을 검증하고 문제를 표로 출력';

    private const DATA_PATH = __DIR__ . '/../../../../RPGProject/data';

    /** BattleEngine이 실제로 aVars/bVars에 채워주는 공식 변수 - 이 밖의 이름은 항상 0으로 계산된다. */
    private const KNOWN_FORMULA_VARS = ['atk', 'def', 'mat', 'mdf', 'mhp', 'mp'];

    /** FrontEnd/src/battle/characters.ts의 SV_MOTION_INDEX와 동일(MZ 표준 18개 모션). */
    private const KNOWN_MOTIONS = [
        'walk', 'wait', 'chant', 'guard', 'damage', 'evade', 'thrust', 'swing',
        'missile', 'skill', 'spell', 'item', 'escape', 'victory', 'dying',
        'abnormal', 'sleep', 'dead',
    ];

    /** SkillSeeder::SCOPE_MAP과 동일한 키 - 이 밖의 문자열은 조용히 oneEnemy(1)로 폴백된다. */
    private const KNOWN_SCOPES = [
        'oneEnemy', 'allEnemies', 'oneAlly', 'allAllies', 'user',
        'enemyFrontRow', 'enemyBackRow', 'allyFrontRow', 'allyBackRow',
    ];

    /** SkillSeeder::DAMAGE_TYPE_MAP과 동일한 키. */
    private const KNOWN_DAMAGE_TYPES = ['none', 'hpDamage', 'mpDamage', 'hpRecover', 'mpRecover', 'hpDrain', 'mpDrain'];

    /** BattleEngine::resolveOneHit()가 실제로 처리하는 damage.type만(그 외는 아무 효과도 없음). */
    private const HANDLED_DAMAGE_TYPES = ['none', 'hpDamage', 'hpRecover'];

    /** SkillSeeder::HIT_TYPE_MAP과 동일한 키. */
    private const KNOWN_HIT_TYPES = ['certain', 'physical', 'magical'];

    /** RPGEditor SkillUsablePosition 직렬화 값. */
    private const KNOWN_USABLE_POSITIONS = ['any', 'front', 'back'];

    /** 어느 직업의 learnings에도 없어도 정상인 공용 스킬(0=공격, 1=자리 이동). */
    private const PUBLIC_SKILL_IDS = [0, 1];

    public function handle(): int
    {
        $skills = $this->readJson('Skills.json');
        $states = $this->readJson('States.json');
        $classes = $this->readJson('Classes.json');
        $types = $this->readJson('Types.json');

        $stateNames = collect($states)->pluck('name', 'id')->all(); // id => name
        $elementCount = count($types['elements'] ?? []);

        $rows = [];

        $rows = [...$rows, ...$this->checkDuplicateStateNames($states)];

        // skillId -> [classId 목록] (더미/공용 스킬은 어느 learnings에도 없을 수 있음).
        $owners = [];
        foreach ($classes as $class) {
            foreach ($class['learnings'] ?? [] as $l) {
                $owners[$l['skillId']][] = $class['id'];
            }
        }
        $skillIds = collect($skills)->pluck('id')->all();
        foreach ($owners as $skillId => $classIds) {
            if (! in_array($skillId, $skillIds, true)) {
                $rows[] = [$skillId, '(learnings)', 'Classes.json learnings가 존재하지 않는 스킬 id를 참조: ' . implode(',', $classIds)];
            } elseif (count($classIds) > 1) {
                $rows[] = [$skillId, '(learnings)', '이 스킬을 여러 직업이 동시에 배움: ' . implode(',', $classIds)];
            }
        }

        $checked = 0;
        foreach ($skills as $skill) {
            if (str_starts_with($skill['name'], '-----')) {
                continue;
            }

            $checked++;
            $issues = $this->validateSkill($skill, $stateNames, $elementCount, array_key_exists($skill['id'], $owners));

            foreach ($issues as $issue) {
                $rows[] = [$skill['id'], $skill['name'], $issue];
            }
        }

        if ($rows === []) {
            $this->info("스킬 {$checked}개 전부 통과 - 문제 없음.");

            return self::SUCCESS;
        }

        $this->table(['id', '이름', '문제'], $rows);
        $skillIssueCount = count(array_filter($rows, fn ($r) => is_int($r[0])));
        $this->warn(count($rows) . '개 문제 발견 (스킬 ' . $checked . '개 중 ' . count(array_unique(array_column(
            array_filter($rows, fn ($r) => is_int($r[0])), 0
        ))) . '개 스킬에 분포, 전역 문제 ' . (count($rows) - $skillIssueCount) . '개).');

        return self::FAILURE;
    }

    /** @return array<int, array{0:int|string,1:string,2:string}> */
    private function validateSkill(array $skill, array $stateNames, int $elementCount, bool $hasOwner): array
    {
        $issues = [];
        $stateIds = array_keys($stateNames);

        $formula = $skill['damage']['formula'] ?? '0';
        $sampleA = ['atk' => 20, 'def' => 15, 'mat' => 20, 'mdf' => 15, 'mhp' => 100, 'mp' => 30];
        $sampleB = ['atk' => 15, 'def' => 15, 'mat' => 15, 'mdf' => 15, 'mhp' => 100, 'mp' => 30];

        // 1. 공식 파싱
        try {
            MzFormulaEvaluator::evaluate($formula, $sampleA, $sampleB);
        } catch (\Throwable $e) {
            $issues[] = "공식 파싱 실패({$formula}): {$e->getMessage()}";
        }

        // 2. 공식이 참조하는 미지 변수 - BattleEngine엔 없어서 항상 0으로 계산됨(예: a.luk)
        if (preg_match_all('/[ab]\.(\w+)/', $formula, $m)) {
            foreach (array_unique($m[1]) as $param) {
                if (! in_array($param, self::KNOWN_FORMULA_VARS, true)) {
                    $issues[] = "공식이 알 수 없는 변수 참조(항상 0으로 계산됨): {$param}";
                }
            }
        }

        // 3. scope/damage.type/hitType/usablePosition/motion - SkillSeeder가 모르는
        // 문자열이면 예외 없이 조용히 기본값으로 폴백되므로 여기서 잡아야 한다.
        $scope = $skill['scope'] ?? null;
        if (! in_array($scope, self::KNOWN_SCOPES, true)) {
            $issues[] = "알 수 없는 scope \"{$scope}\" - SkillSeeder가 조용히 oneEnemy로 취급함";
        }

        $damageType = $skill['damage']['type'] ?? null;
        if (! in_array($damageType, self::KNOWN_DAMAGE_TYPES, true)) {
            $issues[] = "알 수 없는 damage.type \"{$damageType}\" - SkillSeeder가 조용히 none으로 취급함";
        } elseif (! in_array($damageType, self::HANDLED_DAMAGE_TYPES, true)) {
            $issues[] = "damage.type \"{$damageType}\"는 BattleEngine::resolveOneHit()가 처리하지 않음(피해/회복 없이 명중 판정만 일어남)";
        }

        $hitType = $skill['invocation']['hitType'] ?? null;
        if (! in_array($hitType, self::KNOWN_HIT_TYPES, true)) {
            $issues[] = "알 수 없는 invocation.hitType \"{$hitType}\" - SkillSeeder가 조용히 physical로 취급함";
        }

        $usablePosition = $skill['usablePosition'] ?? null;
        if (! in_array($usablePosition, self::KNOWN_USABLE_POSITIONS, true)) {
            $issues[] = "알 수 없는 usablePosition \"{$usablePosition}\"";
        }

        $motion = $skill['motion'] ?? '';
        if ($motion !== '' && ! in_array($motion, self::KNOWN_MOTIONS, true)) {
            $issues[] = "motion에 알 수 없는 모션 이름: {$motion}";
        }

        $elementId = $skill['damage']['elementId'] ?? 0;
        if ($elementId < 0 || $elementId >= $elementCount) {
            $issues[] = "damage.elementId {$elementId}가 Types.json elements 범위(0~" . ($elementCount - 1) . ') 밖';
        }

        // 4. 상태 참조 필드 - 존재하지 않는 id를 가리키면 SkillSeeder가 마이그레이션
        // 시점에 예외를 던지지만(require_target_state 등), damageBonusStateId처럼
        // 검증 안 하는 필드도 있어 전부 직접 확인한다.
        $stateFields = [
            'requiredStateId' => '요구 상태(RequiredStateId)',
            'requireTargetStateId' => '대상 요구 상태(RequireTargetStateId)',
            'selfHasStateId' => '자기 조건 상태(SelfHasStateId)',
            'selfHasAppliesStateId' => '자기 조건 부여 상태(SelfHasAppliesStateId)',
            'targetHasStateId' => '대상 조건 상태(TargetHasStateId)',
            'targetHasAppliesStateId' => '대상 조건 부여 상태(TargetHasAppliesStateId)',
            'damageBonusStateId' => '피해 보너스 조건 상태(DamageBonusStateId)',
        ];
        foreach ($stateFields as $field => $label) {
            $id = $skill[$field] ?? null;
            if ($id !== null && ! in_array($id, $stateIds, true)) {
                $issues[] = "{$label}가 존재하지 않는 상태 id를 참조: {$id}";
            }
        }

        // 5. effects[](사용효과 - 상태 부여/해제) 기반 상태 참조
        $stateEffectKinds = ['targetAddState' => '대상 상태 부여', 'targetRemoveState' => '대상 상태 해제', 'userAddState' => '자기 상태 부여', 'userRemoveState' => '자기 상태 해제'];
        foreach ($skill['effects'] ?? [] as $effect) {
            $kind = $effect['kind'] ?? null;
            if (! array_key_exists($kind, $stateEffectKinds)) {
                continue;
            }
            $stateId = $effect['stateId'] ?? null;
            if ($stateId === null || ! in_array($stateId, $stateIds, true)) {
                $issues[] = "사용효과 \"{$stateEffectKinds[$kind]}\"이 존재하지 않는 상태 id를 참조: " . ($stateId ?? 'null');
            }
            $chance = $effect['chance'] ?? 100;
            if ($chance < 0 || $chance > 100) {
                $issues[] = "사용효과 \"{$stateEffectKinds[$kind]}\"의 chance가 0~100 범위 밖: {$chance}";
            }
        }

        // 6. 짝을 이뤄야 하는 필드 중 하나만 채워진 경우 - SkillSeeder가 둘 다 non-null일
        // 때만 태그를 만들어서, 하나라도 비어 있으면 조용히 아무 효과도 없다.
        $this->checkPair($issues, $skill, 'selfHasStateId', 'selfHasAppliesStateId', '자기 조건부 상태 부여(SelfHasStateId/SelfHasAppliesStateId)');
        $this->checkPair($issues, $skill, 'targetHasStateId', 'targetHasAppliesStateId', '대상 조건부 상태 부여(TargetHasStateId/TargetHasAppliesStateId)');
        $this->checkPair($issues, $skill, 'damageBonusStateId', 'damageBonusPercent', '피해 보너스(DamageBonusStateId/DamageBonusPercent)', allowZero: true);

        // 7. 한쪽만 켜져 있어 죽어있는 나머지 데이터
        if (($skill['scaleWithRemovedDebuffCountAmount'] ?? null) !== null && ! ($skill['cleanseAllyDebuffs'] ?? false)) {
            $issues[] = 'CleanseAllyDebuffs가 꺼져 있어 ScaleWithRemovedDebuffCountAmount가 아무 효과 없음';
        }
        if (($skill['removeRequiredStateOnUse'] ?? false) && ($skill['requiredStateId'] ?? null) === null) {
            $issues[] = 'RemoveRequiredStateOnUse가 켜져 있지만 RequiredStateId가 없어 아무 효과 없음';
        }
        if (($skill['lifestealPercent'] ?? null) !== null && $damageType !== 'hpDamage') {
            $issues[] = "LifestealPercent가 설정됐지만 damage.type이 \"{$damageType}\"라 실제 피해가 없어(hpDamage만 흡혈 가능) 아무 효과 없음";
        }
        if (($skill['scaleWithTargetMpAmount'] ?? null) !== null && ! in_array($damageType, self::HANDLED_DAMAGE_TYPES, true)) {
            $issues[] = "ScaleWithTargetMpAmount가 설정됐지만 damage.type \"{$damageType}\"는 처리되지 않아 아무 효과 없음";
        }

        // 8. TargetDeadAllies가 적 대상 scope에 붙으면 SkillSeeder가 scope를 강제로
        // 아군(전투불능) 대상으로 바꿔버린다(원래 scope 의도가 무시됨).
        if (($skill['targetDeadAllies'] ?? false) && ! in_array($scope, ['oneAlly', 'allAllies'], true)) {
            $issues[] = "TargetDeadAllies가 켜져 있는데 scope가 \"{$scope}\"임 - SkillSeeder가 scope를 강제로 아군(전투불능) 대상으로 바꿔버림";
        }

        // 9. CleanseAllyDebuffs의 추가 피해는 이 스킬의 실제 대상에게 들어간다 - scope가
        // 적이 아니면(자신/아군) 설계 의도와 다를 수 있어 알려준다.
        if (($skill['cleanseAllyDebuffs'] ?? false) && ! in_array($scope, ['oneEnemy', 'allEnemies', 'enemyFrontRow', 'enemyBackRow'], true)) {
            $issues[] = "CleanseAllyDebuffs가 켜져 있는데 scope가 \"{$scope}\"임 - 해제 개수 비례 추가 피해가 아군/자신에게 들어감(의도한 설계인지 확인)";
        }

        // 10. 기본 수치 범위
        if (($skill['mpCost'] ?? 0) < 0) {
            $issues[] = 'mpCost가 음수';
        }
        $variance = $skill['damage']['variance'] ?? 0;
        if ($variance < 0 || $variance > 100) {
            $issues[] = "damage.variance가 0~100 범위 밖: {$variance}";
        }
        $successRate = $skill['invocation']['successRate'] ?? 100;
        if ($successRate < 0 || $successRate > 100) {
            $issues[] = "invocation.successRate가 0~100 범위 밖: {$successRate}";
        }
        if (($skill['invocation']['repeatCount'] ?? 1) < 1) {
            $issues[] = 'invocation.repeatCount가 1 미만';
        }
        if (($skill['castingTurns'] ?? null) !== null && $skill['castingTurns'] < 1) {
            $issues[] = 'castingTurns가 1 미만(0/음수는 사실상 캐스팅 없음과 동일 - null 권장)';
        }
        if (($skill['cooldownTurns'] ?? null) !== null && $skill['cooldownTurns'] < 1) {
            $issues[] = 'cooldownTurns가 1 미만';
        }

        // 11. 고아 스킬 - 공용 스킬(0/1)도 아니고 어느 직업도 배우지 않음
        if (! $hasOwner && ! in_array($skill['id'], self::PUBLIC_SKILL_IDS, true)) {
            $issues[] = '어느 직업의 learnings에도 없고 공용 스킬(0/1)도 아님 - 실제로 아무도 배울 수 없음';
        }

        return $issues;
    }

    /**
     * @param  array<int, string>  $issues
     *
     * $allowZero=true면 두 값이 "둘 다 null"인 정상 상태와 "하나는 0(=falsy지만 유효한
     * 값), 하나는 null"인 상태를 구분해서 null 여부만으로 비교한다(percent=0은 의도적으로
     * "보너스 없음"을 명시한 값일 수 있어 0 자체는 문제 삼지 않는다).
     */
    private function checkPair(array &$issues, array $skill, string $fieldA, string $fieldB, string $label, bool $allowZero = false): void
    {
        $a = $skill[$fieldA] ?? null;
        $b = $skill[$fieldB] ?? null;
        if (($a === null) !== ($b === null)) {
            $missing = $a === null ? $fieldA : $fieldB;
            $issues[] = "{$label} 중 {$missing}만 비어 있음 - 짝이 없어 조용히 무시됨";
        }
    }

    /** @return array<int, array{0:string,1:string,2:string}> */
    private function checkDuplicateStateNames(array $states): array
    {
        $issues = [];
        $counts = [];
        foreach ($states as $state) {
            $counts[$state['name']][] = $state['id'];
        }
        foreach ($counts as $name => $ids) {
            if (count($ids) > 1) {
                $issues[] = ['(states)', $name, 'States.json에 같은 이름이 여러 id에 중복됨: ' . implode(',', $ids) . ' - BattleEngine은 상태를 이름으로 찾아서 앞의 것만 쓰임'];
            }
        }

        return $issues;
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
