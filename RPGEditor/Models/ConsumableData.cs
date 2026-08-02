using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class ConsumableData : ObservableObject
{
    [ObservableProperty]
    private ItemScope scope = ItemScope.OneEnemy;

    public SkillInvocation Invocation { get; set; } = new();

    public SkillDamage Damage { get; set; } = new();

    public ObservableCollection<SkillEffect> Effects { get; set; } = [];

    [ObservableProperty]
    private int craftingCost;

    [ObservableProperty]
    private int craftingTime;

    public ObservableCollection<CraftMaterial> CraftingMaterials { get; set; } = [];

    /// <summary>대상 후보에서 사용자 자신을 제외("다른 아군에게만 사용 가능", 대상 범위가 아군 1인일 때만 의미 있음).</summary>
    [ObservableProperty]
    private bool excludeSelf;

    /// <summary>사용 즉시 대상에게 부여할 상태. null이면 없음.</summary>
    [ObservableProperty]
    private int? applyStateId;

    /// <summary>부여할 상태의 지속 턴수 커스텀 오버라이드. null이면 상태 자신의 기본 지속시간 사용.</summary>
    [ObservableProperty]
    private int? applyStateTurns;
}
