using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class CraftMaterial : ObservableObject
{
    /// <summary>Items.json 참조 ID.</summary>
    [ObservableProperty]
    private int itemId;

    [ObservableProperty]
    private int quantity = 1;
}
