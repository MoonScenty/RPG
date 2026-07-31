namespace RPGEditor.Models;

/// <summary>
/// 지금까지 각 스키마에서 "(Types.json)"으로 참조해 온 대상들.
/// Skill.SkillTypeId, SkillDamage.ElementId, WeaponData.WeaponTypeId,
/// ArmorData.ArmorTypeId, ArmorData.EquipTypeId가 이 그룹들을 참조한다.
/// </summary>
public enum TypeGroup
{
    SkillType,
    Element,
    WeaponType,
    ArmorType,
    EquipType,
}
