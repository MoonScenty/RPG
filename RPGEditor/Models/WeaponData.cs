using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class WeaponData : ObservableObject
{
    /// <summary>Types.json 참조 무기 유형 ID.</summary>
    [ObservableProperty]
    private int weaponTypeId;

    /// <summary>공격 시 재생할 Animations.json ID.</summary>
    [ObservableProperty]
    private int animationId;

    public EquipParams Params { get; set; } = new();

    public List<object> Traits { get; set; } = [];
}
