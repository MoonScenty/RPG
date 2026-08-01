<?php

namespace Database\Seeders;

use App\Models\MzAnimation;
use App\Models\Unit;
use App\Support\MzNoteTagParser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * mz_project/data/{Skills,States,Enemies,Troops}.json을 그대로 읽어
 * mz_skills/mz_states/mz_enemies/mz_troops/mz_troop_members를 채운다.
 * id는 원본과 1:1로 고정(effects[].dataId, learnings[].skillId, members[].enemyId가
 * 숫자로 참조하기 때문). 재실행해도 안전하게 매번 전체를 지우고 다시 채운다.
 *
 * mz_classes는 이 시더가 아니라 ClassSeeder가 채운다(Actors.json을 참조하는
 * ActorSeeder가 params까지 필요해서 별도 시더로 분리됨) - 여기서는 스킬의
 * class_id를 역산하는 데 필요한 Classes.json 원본만 읽어서 참조한다.
 */
class MzImportSeeder extends Seeder
{
    private const MZ_DATA_PATH = __DIR__ . '/../../../mz_project/data';

    public function run(): void
    {
        $classes = $this->readJson('Classes.json');
        $skills = $this->readJson('Skills.json');
        $states = $this->readJson('States.json');
        $enemies = $this->readJson('Enemies.json');
        $troops = $this->readJson('Troops.json');
        $system = $this->readJson('System.json');

        DB::table('mz_troop_members')->delete();
        DB::table('mz_troops')->delete();
        DB::table('mz_skills')->delete();
        DB::table('mz_states')->delete();
        DB::table('mz_enemies')->delete();
        DB::table('mz_system_audio')->delete();

        $stateNames = $this->importStates($states);
        $this->importSkills($skills, $classes, $states, $stateNames);
        $this->importEnemies($enemies);
        $this->importTroops($troops);
        $this->syncEnemyUnits($enemies);
        $this->importSystemAudio($system);

        $this->command?->info('mz_skills/mz_states/mz_enemies/mz_troops/mz_system_audio 임포트 완료.');
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

    /** @return array<int, string> state id -> name (임포트 중 검증/effects 해석에 재사용) */
    private function importStates(array $states): array
    {
        $rows = [];
        $names = [];
        foreach ($states as $state) {
            if ($state === null || $state['id'] < 1 || $state['id'] > 200) {
                continue;
            }

            $names[$state['id']] = $state['name'];
            $rows[] = [
                'id' => $state['id'],
                'name' => $state['name'],
                'note' => $state['note'] ?: null,
                'is_buff' => $state['id'] > 100,
                'auto_removal_timing' => $state['autoRemovalTiming'],
                'min_turns' => $state['minTurns'],
                'max_turns' => $state['maxTurns'],
                'motion' => $state['motion'] ?? 0,
                'priority' => $state['priority'] ?? 0,
                'traits' => json_encode($state['traits'] ?? []),
                'tags' => json_encode(MzNoteTagParser::parseStateTags($state['note'] ?? '')),
            ];
        }
        DB::table('mz_states')->insert($rows);

        return $names;
    }

    /** @param array<int, string> $stateNames */
    private function importSkills(array $skills, array $classes, array $states, array $stateNames): void
    {
        // skillId -> classId (구분선/공용 스킬은 어느 learnings에도 없음 -> class_id null)
        $classOfSkill = [];
        foreach ($classes as $class) {
            if ($class === null) {
                continue;
            }
            foreach ($class['learnings'] as $learning) {
                $classOfSkill[$learning['skillId']] = $class['id'];
            }
        }

        $referencedNames = [];
        $rows = [];

        foreach ($skills as $skill) {
            // 132("캐스팅")는 GambitCatalog::CASTING_SKILL_NAME - 실제 배틀 로직에선
            // 절대 선택되지 않고, 캐스팅(시전 대기) 턴 연출용 애니메이션만 담아두는
            // 가짜 스킬이라 id 상한을 하나 더 늘려서 같이 임포트한다.
            if ($skill === null || $skill['id'] < 2 || $skill['id'] > 132) {
                continue;
            }
            if (str_starts_with($skill['name'], '-----')) {
                continue; // 에디터 목록 구분용 더미 엔트리 - 실효과 없음
            }

            $note = $skill['note'] ?? '';
            $tags = MzNoteTagParser::parseSkillTags($note);
            $tags['target_add_states'] = $this->resolveTargetAddStates($skill['effects'] ?? [], $stateNames);
            $tags['target_remove_states'] = $this->resolveTargetRemoveStates($skill['effects'] ?? [], $stateNames);

            // <CircleImage: 배율, 이름> -> {scale, name} 구조로 정리(캐스팅 스킬 전용,
            // BattleEngine::log()가 캐스팅 턴마다 이 정보를 battle_logs 행에 그대로 박아둔다).
            if ($tags['circle_image'] !== null) {
                [$scale, $imageName] = $tags['circle_image'];
                $tags['circle_image'] = ['scale' => (float) $scale, 'name' => $imageName];
            }

            // message1도 네이티브 필드(에디터의 "사용 메시지1") - MZ 표준 %1(사용자)/%2(스킬명)
            // 치환 문법 그대로 저장해두고, GambitCatalog::CASTING_SKILL_NAME("캐스팅")
            // 스킬 한정으로 BattleScene.ts가 캐스팅 턴 로그 문구를 여기서 가져온다.
            if (($skill['message1'] ?? '') !== '') {
                $tags['message1'] = $skill['message1'];
            }

            foreach ([
                $tags['remove_self_states'], $tags['remove_target_states'],
                array_filter([$tags['require_target_state'], $tags['require_self_state'], $tags['apply_self_state']]),
                $tags['apply_target_if_has'] ?? [], $tags['apply_self_if_has'] ?? [],
            ] as $names) {
                array_push($referencedNames, ...$names);
            }

            $rows[] = [
                'id' => $skill['id'],
                'class_id' => $classOfSkill[$skill['id']] ?? null,
                'name' => $skill['name'],
                'mp_cost' => $skill['mpCost'],
                'tp_cost' => $skill['tpCost'],
                'scope' => $skill['scope'],
                'hit_type' => $skill['hitType'],
                'stype_id' => $skill['stypeId'],
                'occasion' => $skill['occasion'],
                'damage_type' => $skill['damage']['type'],
                'critical' => $skill['damage']['critical'] ?? false,
                'damage_formula' => $skill['damage']['formula'],
                'variance' => $skill['damage']['variance'],
                'element_id' => $skill['damage']['elementId'],
                'repeats' => $skill['repeats'],
                'success_rate' => $skill['successRate'],
                'tags' => json_encode($tags),
            ];
        }

        DB::table('mz_skills')->insert($rows);

        $missing = array_diff(array_unique($referencedNames), $stateNames);
        if ($missing !== []) {
            throw new \RuntimeException('스킬 노트태그가 존재하지 않는 상태를 참조합니다: ' . implode(', ', $missing));
        }
    }

    /** mz_animations.name(에디터 한글 이름) -> effect_name(.efkefc 파일명). */
    private function effectNameByAnimationName(): array
    {
        return MzAnimation::pluck('effect_name', 'name')->all();
    }

    /** effects[].code21(ADD_STATE)만 대상 - dataId(상태 id)를 이름으로 바꿔서 저장(프로젝트 컨벤션: 이름으로 참조). */
    private function resolveTargetAddStates(array $effects, array $stateNames): array
    {
        $out = [];
        foreach ($effects as $effect) {
            if (($effect['code'] ?? null) !== 21) {
                continue;
            }
            $stateId = $effect['dataId'];
            if (! isset($stateNames[$stateId])) {
                continue;
            }
            $out[] = ['state' => $stateNames[$stateId], 'chance' => $effect['value1']];
        }

        return $out;
    }

    /**
     * effects[].code22(REMOVE_STATE)만 대상 - resolveTargetAddStates()와 완전히 같은
     * 패턴. MZ 에디터의 "사용효과 > 상태 탭 > 상태 해제"로 넣은 항목이 여기로 들어온다 -
     * <RemoveTargetState: 상태명>(무조건 100% 제거) 노트태그와 달리 확률(value1)이
     * 붙는다는 점, 그리고 에디터 UI에서 직접 상태를 골라 넣을 수 있다는 점이 다르다.
     * 서로 배타적이지 않고 한 스킬에 둘 다 있으면 둘 다 적용된다.
     */
    private function resolveTargetRemoveStates(array $effects, array $stateNames): array
    {
        $out = [];
        foreach ($effects as $effect) {
            if (($effect['code'] ?? null) !== 22) {
                continue;
            }
            $stateId = $effect['dataId'];
            if (! isset($stateNames[$stateId])) {
                continue;
            }
            $out[] = ['state' => $stateNames[$stateId], 'chance' => $effect['value1']];
        }

        return $out;
    }

    private function importEnemies(array $enemies): void
    {
        $rows = [];
        foreach ($enemies as $enemy) {
            if ($enemy === null) {
                continue;
            }
            // params: [MHP, MMP, ATK, DEF, MAT, MDF, AGI, LUK] (MZ 표준 순서)
            $p = $enemy['params'];
            $rows[] = [
                'id' => $enemy['id'],
                'name' => $enemy['name'],
                'battler_name' => $enemy['battlerName'],
                'note' => $enemy['note'] ?: null,
                'mhp' => $p[0], 'mmp' => $p[1], 'atk' => $p[2], 'def' => $p[3],
                'mat' => $p[4], 'mdf' => $p[5], 'agi' => $p[6], 'luk' => $p[7],
                'actions' => json_encode($enemy['actions'] ?? []),
                'traits' => json_encode($enemy['traits'] ?? []),
            ];
        }
        DB::table('mz_enemies')->insert($rows);
    }

    private function importTroops(array $troops): void
    {
        $troopRows = [];
        $memberRows = [];
        foreach ($troops as $troop) {
            if ($troop === null) {
                continue;
            }
            $troopRows[] = ['id' => $troop['id'], 'name' => $troop['name'] ?: "트룹 {$troop['id']}"];

            // MZ 원본은 x/y 픽셀 좌표로 배치하지만 우리 화면은 6슬롯 고정이라
            // members 배열 순서(1부터)를 그대로 슬롯 번호로 쓴다.
            foreach (array_values($troop['members']) as $index => $member) {
                if ($index >= 6) {
                    break; // 슬롯은 최대 6개
                }
                $memberRows[] = [
                    'troop_id' => $troop['id'],
                    'enemy_id' => $member['enemyId'],
                    'position' => $index + 1,
                ];
            }
        }

        DB::table('mz_troops')->insert($troopRows);
        if ($memberRows !== []) {
            DB::table('mz_troop_members')->insert($memberRows);
        }
    }

    /**
     * System.json의 battleBgm/victoryMe/defeatMe(battleback1Name/2Name과 같은 위치의
     * 프로젝트 전역 기본값) - "name" 필드(트랙 파일명, 확장자 없음)만 저장한다.
     * attackMotions(무기 종류별 기본 공격 모션)도 원본 배열째로 같이 저장 -
     * ActorSeeder가 액터의 초기 장착 무기 wtypeId로 조회해서 units.attack_motion을 정한다.
     */
    private function importSystemAudio(array $system): void
    {
        DB::table('mz_system_audio')->insert([
            'id' => 1,
            'battle_bgm' => $system['battleBgm']['name'] ?: null,
            'victory_me' => $system['victoryMe']['name'] ?: null,
            'defeat_me' => $system['defeatMe']['name'] ?: null,
            'attack_motions' => json_encode($system['attackMotions'] ?? []),
        ]);
    }

    /**
     * 이름이 있는(빈 슬롯 아닌) 적마다 units(type=enemy) 행을 자동 upsert - BattleEngine이
     * 여기서 스폰한다. battle_units.unit_id가 RESTRICT라(과거 전투 기록이 있으면 삭제 불가)
     * 지우고 다시 만들 수 없어서, 이름으로 기존 행을 찾아 갱신한다(mz_enemy_id 도입 전
     * 수동 시더가 만든 "고블린" 행도 이름이 같아서 그대로 이어받는다).
     *
     * note의 <AttackAnimation: 이름> 태그(MzNoteTagParser::parseEnemyTags())를 읽어
     * units.weapon_effect_name에도 반영한다 - 컬럼명은 "무기"지만 아군(장착 무기)/
     * 적(이 노트태그) 어느 쪽에서 온 값이든 frontend/src/battle/BattleScene.ts가 똑같이
     * "일반 공격 시 재생할 이펙트"로만 취급하므로 컬럼을 나누지 않고 재사용했다.
     */
    private function syncEnemyUnits(array $enemies): void
    {
        $effectNameByAnimationName = $this->effectNameByAnimationName();
        $missingAnimationNames = [];

        foreach ($enemies as $enemy) {
            if ($enemy === null || $enemy['name'] === '') {
                continue;
            }

            $tags = MzNoteTagParser::parseEnemyTags($enemy['note'] ?? '');
            $weaponEffectName = null;
            if ($tags['attack_animation_name'] !== null) {
                $weaponEffectName = $effectNameByAnimationName[$tags['attack_animation_name']] ?? null;
                if ($weaponEffectName === null) {
                    $missingAnimationNames[] = $tags['attack_animation_name'];
                }
            }

            $motionMap = [];
            foreach ($tags['dragonbones_motions'] as [$motionName, $clipName]) {
                $motionMap[$motionName] = $clipName;
            }

            $p = $enemy['params'];
            Unit::updateOrCreate(
                ['type' => 'enemy', 'name' => $enemy['name']],
                [
                    'mz_enemy_id' => $enemy['id'],
                    'sprite' => 'Enemy:' . $enemy['battlerName'],
                    'attack_motion' => 'thrust',
                    'weapon_effect_name' => $weaponEffectName,
                    'dragonbones_skeleton' => $tags['dragonbones_skeleton'],
                    'dragonbones_atlas' => $tags['dragonbones_atlas'],
                    'dragonbones_motions' => $motionMap === [] ? null : $motionMap,
                    'dragonbones_scale' => $tags['dragonbones_scale'],
                    'max_hp' => $p[0], 'max_mp' => $p[1], 'atk' => $p[2], 'def' => $p[3],
                    'mat' => $p[4], 'mdf' => $p[5], 'spd' => $p[6], 'luk' => $p[7],
                ],
            );
        }

        if ($missingAnimationNames !== []) {
            throw new \RuntimeException(
                '적 노트태그가 존재하지 않는 애니메이션을 참조합니다: ' . implode(', ', array_unique($missingAnimationNames)),
            );
        }
    }
}
