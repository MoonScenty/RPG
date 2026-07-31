using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class SkillEffect : ObservableObject
{
    [ObservableProperty]
    private SkillEffectKind kind = SkillEffectKind.TargetRecoverHp;

    /// <summary>회복 계열 효과의 퍼센트(대상 최대치 대비) 값.</summary>
    [ObservableProperty]
    private int percentValue;

    /// <summary>회복 계열 효과의 고정 수치, 또는 ATB 부스트 효과의 값.</summary>
    [ObservableProperty]
    private int flatValue;

    /// <summary>상태 추가/해제 효과가 참조하는 States.json의 ID.</summary>
    [ObservableProperty]
    private int stateId;

    /// <summary>상태 추가 효과의 성공 확률(%).</summary>
    [ObservableProperty]
    private int chance = 100;
}
