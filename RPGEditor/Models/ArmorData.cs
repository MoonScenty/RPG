using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class ArmorData : ObservableObject
{
    /// <summary>Types.json 참조 방어구 유형 ID.</summary>
    [ObservableProperty]
    private int armorTypeId;

    /// <summary>Types.json 참조 장비 유형(부위) ID.</summary>
    [ObservableProperty]
    private int equipTypeId;

    public EquipParams Params { get; set; } = new();

    public List<object> Traits { get; set; } = [];

    [ObservableProperty]
    private int craftingCost;

    [ObservableProperty]
    private int craftingTime;

    public ObservableCollection<CraftMaterial> CraftingMaterials { get; set; } = [];
}
