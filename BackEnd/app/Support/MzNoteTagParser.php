<?php

namespace App\Support;

/**
 * README ### 노트태그 표에 문서화된 이 프로젝트 전용 커스텀 태그(RPG Maker 네이티브
 * 문법 아님)를 파싱한다. 화이트리스트 태그명만 매칭하므로 States.json에 섞여있는
 * `<Note>...` 같은 순수 설명용 텍스트는 자동으로 무시된다.
 */
class MzNoteTagParser
{
    /**
     * CircleImage는 <Casting: n>이 있는 스킬 전용 - 캐스팅 중 화면에 뭘 보여줄지
     * 코드가 아니라 이 데이터로 결정한다. "<CircleImage: 배율, 이름>" 형태(배율은
     * 1이 원본 크기, 이름은 FrontEnd/public/assets/circle/{이름}.png 파일명).
     *
     * @return array<string, mixed>
     */
    public static function parseSkillTags(string $note): array
    {
        return [
            'casting_turns' => self::intValue($note, 'Casting'),
            'cooldown_turns' => self::intValue($note, 'Cooldown'),
            'mp_recover' => self::intValue($note, 'MpRecover'),
            'lifesteal_pct' => self::intValue($note, 'Lifesteal'),
            'gauge_boost' => self::intValue($note, 'GaugeBoost'),
            'consume_all_mp' => self::hasFlag($note, 'ConsumeAllMp'),
            'swap_position' => self::hasFlag($note, 'SwapPosition'),
            'target_front_row' => self::hasFlag($note, 'TargetFrontRow'),
            'target_back_row' => self::hasFlag($note, 'TargetBackRow'),
            'remove_self_states' => self::stringValues($note, 'RemoveState'),
            'remove_target_states' => self::stringValues($note, 'RemoveTargetState'),
            'require_target_state' => self::stringValue($note, 'RequireTargetState'),
            'require_self_state' => self::stringValue($note, 'RequireSelfState'),
            'apply_self_state' => self::stringValue($note, 'ApplySelfState'),
            'apply_target_if_has' => self::pairValue($note, 'ApplyStateIfTargetHas'),
            'apply_self_if_has' => self::pairValue($note, 'ApplySelfStateIfSelfHas'),
            'circle_image' => self::pairValue($note, 'CircleImage'),
        ];
    }

    /**
     * 적 전용 태그. AttackAnimation은 mz_animations.id(Animations.json 원본 ID)를
     * 직접 가리키는 숫자다 - mz_animations.name은 정상(RPG Maker 에디터에 보이는
     * 한글 이름 그대로 저장돼 있음)이지만, 그 에디터의 데이터베이스 창이 애초에
     * "0003 타격/불"처럼 번호와 이름을 나란히 보여주므로 사용자가 숫자를 그대로
     * 노트태그에 옮겨 적는 게 이름 매칭보다 더 직관적이라 ID 참조로 정했다.
     *
     * DragonBonesData/DragonBonesTextureAtlasData는 DB 데이터가 아니라
     * mz_project/img/dragonbones/{값}.json 파일명 자체를 가리키므로 이름-참조
     * 컨벤션(DB row를 ID 대신 이름으로 찾는 규칙) 대상이 아니다 - 그대로 저장했다가
     * 프론트가 파일 fetch에 쓴다.
     *
     * DragonBonesMotion은 "<DragonBonesMotion: 우리쪽모션이름, 스켈레톤 클립이름>"
     * 형태로 한 적에 여러 번 반복 가능하다(우리쪽모션이름은 idle/damage/swing/thrust/
     * missile/dying/dead/victory/frontstep/backstep/pinch 등 FrontEnd/src/battle의 sv
     * 배틀러가 쓰는 것과 같은 이름 - 스켈레톤 클립 이름은 리그마다 제각각이라 이렇게
     * 노트태그로 직접 연결해줘야 한다. 매핑 안 된 모션은 그냥 재생 안 됨(sv 배틀러가
     * 없는 포즈를 조용히 건너뛰는 것과 동일한 방식).
     *
     * DragonBonesScale은 퍼센트(mz_animations.scale과 동일한 관례, 기본 100) - fe의
     * BattleScene.ts가 bounding box 실측으로 자동 정규화한 크기 위에 추가로 곱해지는
     * 배율이다(정규화 자체가 리그마다 화면상 크기를 대략 맞춰줄 뿐이라, 미세 조정은
     * 이 태그로 한다).
     *
     * @return array<string, mixed>
     */
    public static function parseEnemyTags(string $note): array
    {
        return [
            'attack_animation_id' => self::intValue($note, 'AttackAnimation'),
            'dragonbones_skeleton' => self::stringValue($note, 'DragonBonesData'),
            'dragonbones_atlas' => self::stringValue($note, 'DragonBonesTextureAtlasData'),
            'dragonbones_motions' => self::pairValues($note, 'DragonBonesMotion'),
            'dragonbones_scale' => self::intValue($note, 'DragonBonesScale'),
        ];
    }

    /**
     * 소모 아이템 전용. HP/MP 회복·상태 부여/해제는 MZ 네이티브 effects[](code
     * 11/12/21/22)로 표현되므로(ItemSeeder가 직접 파싱) 여기선 그걸로 표현 못 하는
     * 두 가지만 다룬다: ApplySelfState는 부여할 상태에 지속 턴수를 직접 지정할 수
     * 있게(상태 자신의 기본 지속시간과 다르게 쓰고 싶을 때, 예: "저항의 물약"이
     * "상태 이상 차단"을 원래 정의(5턴)와 다르게 2턴만 걸고 싶은 경우) "<ApplySelfState:
     * 상태명, 턴수>" 2인자 형태를 쓴다(턴수 생략 시 상태 자신의 기본 지속시간).
     * ExcludeSelf는 scope 7(1 아군) 아이템에서 시전자 자신을 대상 후보에서 빼서
     * "다른 아군에게만 사용 가능"을 표현한다.
     *
     * @return array<string, mixed>
     */
    public static function parseItemTags(string $note): array
    {
        $raw = self::rawValue($note, 'ApplySelfState');
        $applySelfState = null;
        if ($raw !== null) {
            $parts = array_map('trim', explode(',', $raw));
            $applySelfState = [$parts[0], isset($parts[1]) ? (int) $parts[1] : null];
        }

        return [
            'apply_self_state' => $applySelfState,
            'exclude_self' => self::hasFlag($note, 'ExcludeSelf'),
        ];
    }

    /**
     * 조합식 태그 - 완성품(소모 아이템/무기/방어구)의 note에 붙는다. 연구소(포션류)/
     * 대장간(무기/방어구)에서 이 값으로 제작 가능 여부를 판정한다("<Crafting: 시간(초),
     * [(종류, 재료이름), (종류, 재료이름), ...], 소모 골드>" - 종류는 아이템/무기/방어구
     * 중 하나(재료가 mz_items뿐 아니라 mz_weapons/mz_armors 소속일 수도 있어서 각
     * 원소마다 어느 인벤토리 테이블을 볼지 명시한다). 같은 (종류, 이름) 쌍을 배열에
     * 반복해서 개수를 표현한다(예: "(아이템, 강화 결정), (아이템, 강화 결정)"=2개).
     * 이름은 전부 해당 테이블의 name - 숫자 ID 하드코딩 금지 컨벤션을 그대로 따른다.
     *
     * @return array{seconds: int, materials: array<int, array{type: string, name: string, count: int}>, gold_cost: int}|null
     */
    public static function parseCraftingTag(string $note): ?array
    {
        if (preg_match('/<Crafting:\s*(\d+)\s*,\s*\[(.*?)\]\s*,\s*(\d+)\s*>/s', $note, $m) !== 1) {
            return null;
        }

        $materials = [];
        preg_match_all('/\(\s*([^,()]+)\s*,\s*([^()]+?)\s*\)/', $m[2], $pairs, PREG_SET_ORDER);
        foreach ($pairs as $pair) {
            $type = self::materialTypeFromLabel(trim($pair[1]));
            $name = trim($pair[2]);
            if ($type === null || $name === '') {
                continue;
            }
            $key = $type . ':' . $name;
            if (! isset($materials[$key])) {
                $materials[$key] = ['type' => $type, 'name' => $name, 'count' => 0];
            }
            $materials[$key]['count']++;
        }

        return [
            'seconds' => (int) $m[1],
            'materials' => array_values($materials),
            'gold_cost' => (int) $m[3],
        ];
    }

    private static function materialTypeFromLabel(string $label): ?string
    {
        return match ($label) {
            '아이템' => 'item',
            '무기' => 'weapon',
            '방어구' => 'armor',
            default => null,
        };
    }

    /**
     * 직업 전용. "<PartyHudIcon: n>" - n은 IconSet.png 아이콘 인덱스, 파티 HUD
     * 카드 우측 상단에 그 직업 아이콘으로 그린다(PartyHud.ts 참고).
     *
     * @return array<string, mixed>
     */
    public static function parseClassTags(string $note): array
    {
        return [
            'party_hud_icon' => self::intValue($note, 'PartyHudIcon'),
        ];
    }

    /** @return array<string, mixed> */
    public static function parseStateTags(string $note): array
    {
        return [
            'dot_percent' => self::intValue($note, 'DotPercent'),
            'hot_percent' => self::intValue($note, 'HotPercent'),
            'taunt' => self::hasFlag($note, 'Taunt'),
            'guard_ally' => self::hasFlag($note, 'GuardAlly'),
            'damage_taken_rate' => self::intValue($note, 'DamageTakenRate'),
            'shield_pct' => self::intValue($note, 'Shield'),
            'undying' => self::hasFlag($note, 'Undying'),
        ];
    }

    private static function hasFlag(string $note, string $tag): bool
    {
        return preg_match('/<' . preg_quote($tag, '/') . '(?::[^>]*)?>/', $note) === 1;
    }

    private static function intValue(string $note, string $tag): ?int
    {
        $raw = self::rawValue($note, $tag);

        return $raw !== null ? (int) trim($raw) : null;
    }

    private static function stringValue(string $note, string $tag): ?string
    {
        $raw = self::rawValue($note, $tag);

        return $raw !== null ? trim($raw) : null;
    }

    /** RemoveState/RemoveTargetState는 한 스킬 안에 여러 번 나올 수 있다("반복 가능"). */
    private static function stringValues(string $note, string $tag): array
    {
        preg_match_all('/<' . preg_quote($tag, '/') . ':\s*([^>]+)>/', $note, $matches);

        return array_map('trim', $matches[1]);
    }

    /** "<Tag: A,B>" -> [A, B]. */
    private static function pairValue(string $note, string $tag): ?array
    {
        $raw = self::rawValue($note, $tag);
        if ($raw === null) {
            return null;
        }

        $parts = array_map('trim', explode(',', $raw));

        return count($parts) === 2 ? $parts : null;
    }

    /**
     * pairValue의 반복 가능 버전 - "<Tag: A,B>"가 한 note 안에 여러 번 나올 수 있을 때
     * 전부 [[A,B], [A2,B2], ...]로 모은다(DragonBonesMotion처럼 모션마다 한 줄씩 반복
     * 되는 태그용). B 쪽에 공백이 있어도(예: "Attack A") 그대로 보존된다.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private static function pairValues(string $note, string $tag): array
    {
        preg_match_all('/<' . preg_quote($tag, '/') . ':\s*([^,>]+),\s*([^>]+)>/', $note, $matches, PREG_SET_ORDER);

        return array_map(fn (array $m) => [trim($m[1]), trim($m[2])], $matches);
    }

    private static function rawValue(string $note, string $tag): ?string
    {
        if (preg_match('/<' . preg_quote($tag, '/') . ':\s*([^>]+)>/', $note, $m) === 1) {
            return $m[1];
        }

        return null;
    }
}
