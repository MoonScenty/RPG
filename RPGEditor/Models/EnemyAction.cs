using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

/// <summary>
/// Param1/Param2의 의미는 ConditionType에 따라 달라진다.
/// TurnNumber: Param1=매 N턴, Param2=시작 오프셋 / SelfHpRange, SelfMpRange: Param1=최소%, Param2=최대% / HasState: StateId만 사용.
/// </summary>
public partial class EnemyAction : ObservableObject
{
    [ObservableProperty]
    private EnemyActionConditionType conditionType = EnemyActionConditionType.Always;

    [ObservableProperty]
    private int param1;

    [ObservableProperty]
    private int param2;

    /// <summary>ConditionType이 HasState일 때 참조하는 States.json ID.</summary>
    [ObservableProperty]
    private int stateId;

    [ObservableProperty]
    private int priority = 5;

    /// <summary>Skills.json 참조 ID.</summary>
    [ObservableProperty]
    private int skillId;
}
