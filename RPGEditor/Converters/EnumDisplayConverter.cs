using System.Globalization;
using System.Windows.Data;
using RPGEditor.Models;

namespace RPGEditor.Converters;

/// <summary>열거형 값을 한글 표기로 바꿔주는 콤보박스 표시용 컨버터.</summary>
public class EnumDisplayConverter : IValueConverter
{
    public object Convert(object? value, Type targetType, object parameter, CultureInfo culture)
    {
        return value switch
        {
            ItemKind v => v switch
            {
                ItemKind.Weapon => "무기",
                ItemKind.Armor => "방어구",
                ItemKind.Consumable => "소모품",
                ItemKind.Material => "재료",
                _ => v.ToString(),
            },
            ItemScope v => v switch
            {
                ItemScope.Self => "자신",
                ItemScope.OneEnemy => "적 하나",
                ItemScope.OneAlly => "아군 하나",
                ItemScope.AllEnemies => "적 전체",
                ItemScope.AllAllies => "아군 전체",
                _ => v.ToString(),
            },
            SkillHitType v => v switch
            {
                SkillHitType.Certain => "필중",
                SkillHitType.Physical => "물리",
                SkillHitType.Magical => "마법",
                _ => v.ToString(),
            },
            DamageType v => v switch
            {
                DamageType.None => "없음",
                DamageType.HpDamage => "HP 피해",
                DamageType.MpDamage => "MP 피해",
                DamageType.HpRecover => "HP 회복",
                DamageType.MpRecover => "MP 회복",
                DamageType.HpDrain => "HP 흡수",
                DamageType.MpDrain => "MP 흡수",
                _ => v.ToString(),
            },
            SkillEffectKind v => v switch
            {
                SkillEffectKind.TargetRecoverHp => "대상 HP 회복",
                SkillEffectKind.TargetRecoverMp => "대상 MP 회복",
                SkillEffectKind.UserRecoverHp => "사용자 HP 회복",
                SkillEffectKind.UserRecoverMp => "사용자 MP 회복",
                SkillEffectKind.TargetAddState => "대상 상태 추가",
                SkillEffectKind.TargetRemoveState => "대상 상태 해제",
                SkillEffectKind.UserAddState => "사용자 상태 추가",
                SkillEffectKind.UserRemoveState => "사용자 상태 해제",
                SkillEffectKind.AtbBoost => "ATB 부스트",
                _ => v.ToString(),
            },
            EnemyActionConditionType v => v switch
            {
                EnemyActionConditionType.Always => "항상",
                EnemyActionConditionType.TurnNumber => "순번",
                EnemyActionConditionType.SelfHpRange => "자신 HP 범위",
                EnemyActionConditionType.SelfMpRange => "자신 MP 범위",
                EnemyActionConditionType.HasState => "상태 보유",
                _ => v.ToString(),
            },
            StateRestriction v => v switch
            {
                StateRestriction.None => "없음",
                StateRestriction.AttackEnemy => "적 공격",
                StateRestriction.AttackAlly => "아군 공격",
                StateRestriction.AttackAnyone => "아무나 공격",
                StateRestriction.CannotMove => "행동 불가",
                _ => v.ToString(),
            },
            StateAutoRemovalTiming v => v switch
            {
                StateAutoRemovalTiming.None => "없음",
                StateAutoRemovalTiming.TurnEnd => "턴 종료 시",
                StateAutoRemovalTiming.ActionEnd => "행동 종료 시",
                _ => v.ToString(),
            },
            AnimationDisplayType v => v switch
            {
                AnimationDisplayType.OnEachTarget => "대상별",
                AnimationDisplayType.CenterOfTargets => "대상 중심",
                AnimationDisplayType.CenterOfScreen => "화면 중심",
                _ => v.ToString(),
            },
            SkillScope v => v switch
            {
                SkillScope.OneEnemy => "적 하나",
                SkillScope.AllEnemies => "적 전체",
                SkillScope.OneAlly => "아군 하나",
                SkillScope.AllAllies => "아군 전체",
                SkillScope.User => "사용자",
                SkillScope.EnemyFrontRow => "적 전열",
                SkillScope.EnemyBackRow => "적 후열",
                SkillScope.AllyFrontRow => "아군 전열",
                SkillScope.AllyBackRow => "아군 후열",
                _ => v.ToString(),
            },
            SkillUsablePosition v => v switch
            {
                SkillUsablePosition.Front => "전열",
                SkillUsablePosition.Back => "후열",
                SkillUsablePosition.Any => "무관",
                _ => v.ToString(),
            },
            null => string.Empty,
            _ => value.ToString() ?? string.Empty,
        };
    }

    public object ConvertBack(object value, Type targetType, object parameter, CultureInfo culture)
        => throw new NotSupportedException();
}
