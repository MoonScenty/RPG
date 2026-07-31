using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

/// <summary>장비 착용 시 능력치 변화량. 기본 능력치(hp~luk) + 전투 파생 능력치(atk~statusRes).</summary>
public partial class EquipParams : ObservableObject
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

    [ObservableProperty]
    private int atk;

    [ObservableProperty]
    private int def;

    [ObservableProperty]
    private int matk;

    [ObservableProperty]
    private int mdef;

    [ObservableProperty]
    private int hit;

    [ObservableProperty]
    private int eva;

    [ObservableProperty]
    private int crit;

    [ObservableProperty]
    private int critDamage;

    [ObservableProperty]
    private int statusHit;

    [ObservableProperty]
    private int statusRes;
}
