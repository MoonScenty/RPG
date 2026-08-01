<?php

namespace App\Console\Commands;

use App\Support\MzFormulaEvaluator;
use App\Support\MzNoteTagParser;
use Illuminate\Console\Command;

/**
 * mz_project/data/{Skills,States}.json을 DB를 거치지 않고 직접 읽어서(재시딩 없이
 * 바로 실행 가능) 데이터 정합성만 정적으로 훑는다 - 실제 전투 로직(타겟팅/치명타/
 * ATB 등)은 검증 대상이 아니다. MzImportSeeder는 노트태그가 참조하는 상태 이름이
 * 없으면 예외를 던지고 멈추지만(첫 오류에서 중단), 이 커맨드는 끝까지 돌면서
 * 발견한 문제를 전부 모아서 표로 보여준다 - 그리고 시더가 아예 검사하지 않는
 * 것들(공식의 미지 변수, effects[] 기반 상태 참조, SkillMotion 오타, RequireFront/
 * RequireBack 동시 지정)까지 같이 잡아낸다.
 */
class ValidateSkills extends Command
{
    protected $signature = 'skills:validate';

    protected $description = 'mz_project Skills.json/States.json의 데이터 정합성을 검증하고 문제를 표로 출력';

    /** BattleEngine이 실제로 aVars/bVars에 채워주는 공식 변수 - 이 밖의 이름은 항상 0으로 계산된다. */
    private const KNOWN_FORMULA_VARS = ['atk', 'def', 'mat', 'mdf', 'mhp', 'mp'];

    /** FrontEnd/src/battle/characters.ts의 SV_MOTION_INDEX와 동일(MZ 표준 18개 모션). */
    private const KNOWN_MOTIONS = [
        'walk', 'wait', 'chant', 'guard', 'damage', 'evade', 'thrust', 'swing',
        'missile', 'skill', 'spell', 'item', 'escape', 'victory', 'dying',
        'abnormal', 'sleep', 'dead',
    ];

    /** MzSkill::targetSide()가 실제로 구분하는 scope만 "알려짐"으로 취급. */
    private const KNOWN_SCOPES = [1, 2, 7, 8, 9, 10, 11];

    public function handle(): int
    {
        $dataPath = base_path('../mz_project/data');

        $skills = $this->readJson($dataPath . '/Skills.json');
        $states = $this->readJson($dataPath . '/States.json');

        $stateNames = [];
        foreach ($states as $state) {
            if ($state !== null && $state['id'] >= 1 && $state['id'] <= 200) {
                $stateNames[] = $state['name'];
            }
        }

        $sampleA = ['atk' => 20, 'def' => 15, 'mat' => 20, 'mdf' => 15, 'mhp' => 100, 'mp' => 30];
        $sampleB = ['atk' => 15, 'def' => 15, 'mat' => 15, 'mdf' => 15, 'mhp' => 100, 'mp' => 30];

        $rows = [];
        $checked = 0;

        foreach ($skills as $skill) {
            if ($skill === null || $skill['id'] < 2 || $skill['id'] > 132) {
                continue;
            }
            if (str_starts_with($skill['name'], '-----')) {
                continue;
            }

            $checked++;
            $issues = $this->validateSkill($skill, $stateNames, $sampleA, $sampleB);

            foreach ($issues as $issue) {
                $rows[] = [$skill['id'], $skill['name'], $issue];
            }
        }

        if ($rows === []) {
            $this->info("스킬 {$checked}개 전부 통과 - 문제 없음.");

            return self::SUCCESS;
        }

        $this->table(['id', '이름', '문제'], $rows);
        $this->warn(count($rows) . "개 문제 발견 (스킬 {$checked}개 중 " . count(array_unique(array_column($rows, 0))) . '개 스킬에 분포).');

        return self::FAILURE;
    }

    /** @return array<int, string> */
    private function validateSkill(array $skill, array $stateNames, array $sampleA, array $sampleB): array
    {
        $issues = [];
        $note = $skill['note'] ?? '';
        $formula = $skill['damage']['formula'] ?? '0';

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

        // 3. 노트태그가 참조하는 상태 이름 존재 여부
        $tags = MzNoteTagParser::parseSkillTags($note);
        $referenced = array_merge($tags['remove_self_states'], $tags['remove_target_states']);
        foreach (['require_target_state', 'require_self_state', 'apply_self_state'] as $key) {
            if ($tags[$key] !== null) {
                $referenced[] = $tags[$key];
            }
        }
        foreach (['apply_target_if_has', 'apply_self_if_has'] as $key) {
            if ($tags[$key] !== null) {
                array_push($referenced, ...$tags[$key]);
            }
        }
        foreach (array_unique($referenced) as $name) {
            if (! in_array($name, $stateNames, true)) {
                $issues[] = "노트태그가 존재하지 않는 상태를 참조: {$name}";
            }
        }

        // 4. effects[](사용효과 탭 - 상태 부여/해제) 기반 상태 참조 - MzImportSeeder는
        // dataId가 유효하지 않으면 예외 없이 조용히 스킵하므로 여기서 따로 잡아야 함
        foreach ($skill['effects'] ?? [] as $effect) {
            if (! in_array($effect['code'] ?? null, [21, 22], true)) {
                continue;
            }
            $stateId = $effect['dataId'] ?? null;
            if ($stateId === null || $stateId < 1 || $stateId > 200) {
                $label = $effect['code'] === 21 ? '상태 부여' : '상태 해제';
                $issues[] = "사용효과 \"{$label}\"이 존재하지 않는 상태 id를 참조: {$stateId}";
            }
        }

        // 5. SkillMotion 오타
        if ($tags['skill_motion'] !== null && ! in_array($tags['skill_motion'], self::KNOWN_MOTIONS, true)) {
            $issues[] = "SkillMotion에 알 수 없는 모션 이름: {$tags['skill_motion']}";
        }

        // 6. RequireFront/RequireBack 동시 지정 - 노트태그 파서가 RequireFront를
        // 조용히 우선시켜서 RequireBack이 무시된다
        if (str_contains($note, '<RequireFront>') && str_contains($note, '<RequireBack>')) {
            $issues[] = 'RequireFront/RequireBack이 둘 다 있음 - RequireFront만 적용되고 RequireBack은 무시됨';
        }

        // 7. 알려지지 않은 scope(3-6, 무작위 대상) - targetSide()가 구분 안 하고 기본값(enemy)으로 취급
        $scope = $skill['scope'] ?? null;
        if ($scope !== null && ! in_array($scope, self::KNOWN_SCOPES, true)) {
            $issues[] = "scope {$scope}는 MzSkill::targetSide()가 특별히 구분하지 않음(기본값 enemy로 취급)";
        }

        // 8. TargetFrontRow/TargetBackRow 동시 지정 - restrictToRow()가 전열을 먼저
        // 확인해서 TargetBackRow가 무시된다
        if (str_contains($note, '<TargetFrontRow>') && str_contains($note, '<TargetBackRow>')) {
            $issues[] = 'TargetFrontRow/TargetBackRow가 둘 다 있음 - TargetFrontRow만 적용되고 TargetBackRow는 무시됨';
        }

        // 9. TargetFrontRow/TargetBackRow는 광역기(scope 2/8/10)를 좁히는 용도인데
        // 단일 대상 스킬에 붙이면 대상 후보만 줄어들 뿐(광역이 되진 않음) - 의도와 다를
        // 수 있어 알려준다
        $isAoeScope = in_array($scope, [2, 8, 10], true);
        if (! $isAoeScope && (str_contains($note, '<TargetFrontRow>') || str_contains($note, '<TargetBackRow>'))) {
            $issues[] = 'TargetFrontRow/TargetBackRow는 광역기(scope 2/8/10)용 태그인데 이 스킬은 단일 대상 scope라 대상 후보만 좁아짐(전체 적/아군이 되진 않음)';
        }

        return $issues;
    }

    private function readJson(string $path): array
    {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("mz_project 데이터를 읽지 못했습니다: {$path}");
        }

        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }
}
