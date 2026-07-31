using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class Item : DatabaseEntry
{
    [ObservableProperty]
    private ItemKind kind = ItemKind.Consumable;

    [ObservableProperty]
    private int iconIndex;

    [ObservableProperty]
    private string description = string.Empty;

    [ObservableProperty]
    private int price;

    public ConsumableData Consumable { get; set; } = new();

    public WeaponData Weapon { get; set; } = new();

    public ArmorData Armor { get; set; } = new();
}
