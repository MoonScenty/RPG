using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class CharacterClass : DatabaseEntry
{
    public const int MaxLevel = 99;

    [ObservableProperty]
    private int iconIndex;

    /// <summary>파티 HUD 카드 우측 상단에 표시할 직업 아이콘(IconSet.png 인덱스). null이면 표시 안 함.</summary>
    [ObservableProperty]
    private int? partyHudIcon;

    public ExpCurve ExpCurve { get; set; } = new();

    /// <summary>레벨 1~99 능력치 곡선. 레벨당 한 행.</summary>
    public ObservableCollection<ClassLevelStats> ParamCurve { get; set; } = CreateDefaultParamCurve();

    public ObservableCollection<ClassLearning> Learnings { get; set; } = [];

    public List<object> Traits { get; set; } = [];

    private static ObservableCollection<ClassLevelStats> CreateDefaultParamCurve()
    {
        var curve = new ObservableCollection<ClassLevelStats>();
        for (var level = 1; level <= MaxLevel; level++)
            curve.Add(new ClassLevelStats { Level = level });
        return curve;
    }
}
