<?php

namespace App\Support;

/**
 * 스킬/적/아이템/직업/상태의 값 태그(Casting, AttackAnimation, DotPercent, Crafting
 * 등)는 전부 각 편집 화면의 구조화 필드(TextBox/CheckBox/StateField/AnimationField/
 * CraftingEditor 등)로 대체되어, 이제 남은 건 TargetFrontRow/TargetBackRow뿐이다 -
 * 이것도 사용자가 직접 타이핑하는 게 아니라 SkillSeeder가 스킬 scope(enemyFrontRow
 * 등)에 따라 자동으로 세팅하는 내부 계산용 플래그다.
 */
class MzNoteTagParser
{
    /** @return array<string, mixed> */
    public static function parseSkillTags(string $note): array
    {
        return [
            'target_front_row' => self::hasFlag($note, 'TargetFrontRow'),
            'target_back_row' => self::hasFlag($note, 'TargetBackRow'),
        ];
    }

    private static function hasFlag(string $note, string $tag): bool
    {
        return preg_match('/<' . preg_quote($tag, '/') . '(?::[^>]*)?>/', $note) === 1;
    }
}
