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

    /// <summary>매 턴 최대 HP의 이 %만큼 피해(도트 데미지). null이면 없음.</summary>
    [ObservableProperty]
    private int? dotPercent;

    /// <summary>매 턴 최대 HP의 이 %만큼 회복(지속 회복). null이면 없음.</summary>
    [ObservableProperty]
    private int? hotPercent;

    /// <summary>이 상태를 가진 유닛을 적 AI가 우선 타겟팅하도록 강제(도발).</summary>
    [ObservableProperty]
    private bool taunt;

    /// <summary>같은 편 아군이 받을 피해를 이 유닛이 대신 받는다(감싸기).</summary>
    [ObservableProperty]
    private bool guardAlly;

    /// <summary>받는 피해에 곱해지는 비율(%) - 100=변화 없음, 0=완전 무효, 90=10% 경감. null이면 없음.</summary>
    [ObservableProperty]
    private int? damageTakenRate;

    /// <summary>최대 HP의 이 %만큼 피해를 1회 흡수하고 소멸하는 보호막. null이면 없음.</summary>
    [ObservableProperty]
    private int? shieldAbsorbPercent;

    /// <summary>이 상태를 가진 동안 HP가 1 밑으로 떨어지지 않는다(빈사 방지).</summary>
    [ObservableProperty]
    private bool undying;

    /// <summary>부정적 효과(디버프)인지 - "아군 디버프 전부 해제" 계열 스킬(CleanseAllyDebuffs)이
    /// 어떤 상태를 지울지 판정하는 데 쓰인다. 콤보/원소 태세처럼 중립적인 자기 관리용
    /// 상태나 버프는 false.</summary>
    [ObservableProperty]
    private bool isDebuff;

    /// <summary>이 상태를 가진 동안 시전 중인 스킬의 캐스팅(영창) 소요 턴 수를 이 %만큼
    /// 줄인다(영창 가속). null이면 없음.</summary>
    [ObservableProperty]
    private int? castSpeedRatePercent;

    /// <summary>이 상태를 가진 동안 사용하는 스킬의 흡혈률(Lifesteal)에 이 %를 그대로
    /// 더한다(예: 스킬 자체 흡혈 30% + 이 상태 20% = 50%). null이면 없음.</summary>
    [ObservableProperty]
    private int? lifestealBonusPercent;
}
