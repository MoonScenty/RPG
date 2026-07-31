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
                SkillEffectKind.TargetRecoverTp => "대상 TP 회복",
                SkillEffectKind.UserRecoverHp => "사용자 HP 회복",
                SkillEffectKind.UserRecoverMp => "사용자 MP 회복",
                SkillEffectKind.UserRecoverTp => "사용자 TP 회복",
                SkillEffectKind.TargetAddState => "대상 상태 추가",
                SkillEffectKind.TargetRemoveState => "대상 상태 해제",
                SkillEffectKind.UserAddState => "사용자 상태 추가",
                SkillEffectKind.UserRemoveState => "사용자 상태 해제",
                SkillEffectKind.AtbBoost => "ATB 부스트",
                _ => v.ToString(),
            },
            null => string.Empty,
            _ => value.ToString() ?? string.Empty,
        };
    }

    public object ConvertBack(object value, Type targetType, object parameter, CultureInfo culture)
        => throw new NotSupportedException();
}
