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
}
