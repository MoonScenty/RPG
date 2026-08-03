<?php

namespace App\Support;

use App\Models\MzItem;
use App\Models\MzSkill;
use App\Models\MzState;
use App\Models\Unit;

/**
 * gambit 편집기(용병 행동 규칙)와 BattleEngine이 함께 읽는 스킬/아이템/상태 카탈로그.
 * mz_project에서 임포트한 mz_skills/mz_items/mz_states 기반 - 스킬은 용병마다 자기
 * 직업 스킬(+공용 스킬)만 쓸 수 있지만, 소모 아이템은 직업 구분 없이 전원이 같은
 * 목록을 쓴다(인벤토리/소지 개수 개념은 아직 없음 - 스킬처럼 "쓸 수 있다"만 있음).
 */
class GambitCatalog
{
    /**
     * mz_project/data/Skills.json id 132 - 실제 배틀 로직에선 절대 선택되지 않는
     * 가짜 스킬. 캐스팅(시전 대기) 턴 로그 문구(message1)만 지정해두는 용도라,
     * 진짜 스킬 목록(gambit 드롭다운)에서는 뺀다. 연출(포즈/CircleImage)은 이제
     * 실제 캐스팅 스킬 각각의 태그로 결정되므로 이 더미와 무관.
     */
    public const CASTING_SKILL_NAME = '캐스팅';

    /** 이 용병(직업)이 쓸 수 있는 스킬 - 자기 class_id 소속 + class_id가 null인 공용 스킬(캐스팅 연출용 더미 제외). */
    public static function skillsForUnit(Unit $unit): array
    {
        return MzSkill::where(function ($query) use ($unit) {
                $query->where('class_id', $unit->class_id)->orWhereNull('class_id');
            })
            ->where('name', '!=', self::CASTING_SKILL_NAME)
            ->orderBy('id')
            ->get()
            ->map(fn (MzSkill $skill) => self::describeSkill($skill))
            ->all();
    }

    /**
     * 캐스팅 턴 로그 문구 템플릿(MZ 표준 %1/%2 치환 문법, "캐스팅" 스킬의 message1) -
     * 프론트가 %1은 시전자 이름, %2는 대상 이름으로 바꿔서 보여준다(다른 스킬의
     * message1과 달리 %2가 스킬명이 아님 - 이 스킬 하나가 모든 캐스팅 턴에 재사용되기
     * 때문). 비어있으면 null(프론트가 기본 문구로 대체).
     */
    public static function castingMessage(): ?string
    {
        return MzSkill::where('name', self::CASTING_SKILL_NAME)->first()?->tags['message1'] ?? null;
    }

    private static function describeSkill(MzSkill $skill): array
    {
        return [
            'key' => (string) $skill->id,
            'name' => $skill->name,
            'target_side' => $skill->targetSide(),
            'mp_cost' => $skill->mp_cost,
            'cooldown' => $skill->tags['cooldown_turns'] ?? 0,
            'casting' => $skill->tags['casting_turns'] ?? 0,
        ];
    }

    public static function skillExists(int|string $id): bool
    {
        return is_numeric($id) && MzSkill::whereKey((int) $id)->exists();
    }

    public static function skill(int|string $id): ?MzSkill
    {
        return is_numeric($id) ? MzSkill::find((int) $id) : null;
    }

    /** 소모 아이템 목록 - 직업 구분 없이 전원 동일(스킬과 달리 class_id 필터링 없음). */
    public static function allItems(): array
    {
        // occasion 3(Never) = 조합 재료 등 전투 중 사용 불가 아이템 - 행동 규칙
        // 드롭다운에 노출하지 않는다.
        return MzItem::where('occasion', '!=', 3)
            ->orderBy('id')
            ->get()
            ->map(fn (MzItem $item) => self::describeItem($item))
            ->all();
    }

    private static function describeItem(MzItem $item): array
    {
        return [
            'key' => (string) $item->id,
            'name' => $item->name,
            'target_side' => $item->targetSide(),
            'description' => $item->description,
        ];
    }

    public static function itemExists(int|string $id): bool
    {
        return is_numeric($id) && MzItem::whereKey((int) $id)->exists();
    }

    public static function item(int|string $id): ?MzItem
    {
        return is_numeric($id) ? MzItem::find((int) $id) : null;
    }

    /**
     * 조건 드롭다운용 - 전체 상태 목록(버프/디버프 구분 없음). BattleEngine::
     * stateConditionMet()이 hasState()로 이름만 보고 판정하므로("자신에게 이 상태가
     * 있다/없다"), 버프 상태(예: 흡혈률 상승)도 조건으로 걸 수 있어야 한다 - 예전엔
     * IsDebuff=true인 것만 노출해서 자기 버프 여부를 조건으로 쓸 방법이 없었다.
     */
    public static function allStates(): array
    {
        return MzState::orderBy('id')
            ->get()
            ->map(fn (MzState $state) => ['key' => $state->name, 'label' => $state->name])
            ->all();
    }

    public static function stateExists(string $name): bool
    {
        return MzState::where('name', $name)->exists();
    }
}
