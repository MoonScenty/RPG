using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class Troop : DatabaseEntry
{
    /// <summary>img/battleback1 참조.</summary>
    [ObservableProperty]
    private string battleback1 = string.Empty;

    /// <summary>img/battleback2 참조.</summary>
    [ObservableProperty]
    private string battleback2 = string.Empty;

    /// <summary>Enemies.json 참조 ID. null이면 해당 슬롯에 적 없음.</summary>
    [ObservableProperty]
    private int? frontTopEnemyId;

    [ObservableProperty]
    private int? frontMiddleEnemyId;

    [ObservableProperty]
    private int? frontBottomEnemyId;

    [ObservableProperty]
    private int? backTopEnemyId;

    [ObservableProperty]
    private int? backMiddleEnemyId;

    [ObservableProperty]
    private int? backBottomEnemyId;
}
