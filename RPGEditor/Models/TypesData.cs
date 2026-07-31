using System.Collections.ObjectModel;

namespace RPGEditor.Models;

/// <summary>속성/스킬 유형/무기 유형/방어구 유형/장비 유형. 각 목록의 배열 위치가 곧 참조 ID.</summary>
public class TypesData
{
    public ObservableCollection<string> Elements { get; set; } =
        new(["물리적", "불", "얼음", "천둥", "물", "흙", "바람", "빛", "어둠"]);

    public ObservableCollection<string> SkillTypes { get; set; } =
        new(["마법", "필살기"]);

    public ObservableCollection<string> WeaponTypes { get; set; } =
        new(["검", "활", "지팡이", "홀장", "창", "책", "오브"]);

    public ObservableCollection<string> ArmorTypes { get; set; } =
        new(["일반 방어구", "마법 방어구", "경장비 방어구", "중장비 방어구", "소형 방패", "대형 방패"]);

    public ObservableCollection<string> EquipTypes { get; set; } =
        new(["무기", "방패", "머리", "몸", "장신구"]);
}
