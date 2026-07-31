using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class EnemyStats : ObservableObject
{
    [ObservableProperty]
    private int hp;

    [ObservableProperty]
    private int mp;

    [ObservableProperty]
    private int str;

    [ObservableProperty]
    private int vit;

    [ObservableProperty]
    private int mnd;

    [ObservableProperty]
    private int dex;

    [ObservableProperty]
    private int agi;

    [ObservableProperty]
    private int luk;

    private int intStat;

    /// <summary>지능(INT). "int"는 C# 예약어라 필드명은 intStat을 사용.</summary>
    public int Int
    {
        get => intStat;
        set => SetProperty(ref intStat, value);
    }
}
