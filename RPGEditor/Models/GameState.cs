using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class GameState : DatabaseEntry
{
    [ObservableProperty]
    private int iconIndex;

    /// <summary>같은 대상에 여러 상태가 걸렸을 때 아이콘 표시 우선순위(높을수록 우선).</summary>
    [ObservableProperty]
    private int priority = 50;

    /// <summary>배틀러 주변에 표시되는 상태 오버레이 이펙트 ID. 0=없음.</summary>
    [ObservableProperty]
    private int overlay;

    [ObservableProperty]
    private StateRestriction restriction = StateRestriction.None;

    /// <summary>SV 배틀러 모션 이름.</summary>
    [ObservableProperty]
    private string motion = string.Empty;

    [ObservableProperty]
    private bool removeAtBattleEnd;

    [ObservableProperty]
    private bool removeByRestriction;

    [ObservableProperty]
    private StateAutoRemovalTiming autoRemovalTiming = StateAutoRemovalTiming.None;

    [ObservableProperty]
    private int minTurns = 1;

    [ObservableProperty]
    private int maxTurns = 1;

    [ObservableProperty]
    private bool removeByDamage;

    /// <summary>피해를 받았을 때 해제될 확률(%).</summary>
    [ObservableProperty]
    private int removeByDamageChance = 100;

    /// <summary>%1=대상, %2=시전자.</summary>
    [ObservableProperty]
    private string messageWhenAdded = string.Empty;

    [ObservableProperty]
    private string messageWhenAddedEnemy = string.Empty;

    [ObservableProperty]
    private string messageWhileActive = string.Empty;

    [ObservableProperty]
    private string messageWhenRemoved = string.Empty;

    public List<object> Traits { get; set; } = [];
}
