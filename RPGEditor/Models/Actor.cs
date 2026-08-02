using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class Actor : DatabaseEntry
{
    [ObservableProperty]
    private string battlerName = string.Empty;

    [ObservableProperty]
    private int classId;

    [ObservableProperty]
    private string faceName = string.Empty;

    [ObservableProperty]
    private string hudFaceName = string.Empty;

    [ObservableProperty]
    private int initialLevel = 1;

    [ObservableProperty]
    private int maxLevel = 99;

    [ObservableProperty]
    private string profile = string.Empty;

    /// <summary>[무기, 방패, 머리, 몸, 장신구] 순서의 아이템 ID 5개.</summary>
    public int[] Equips { get; set; } = new int[5];

    public List<object> Traits { get; set; } = [];
}
