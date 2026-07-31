using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class SkillInvocation : ObservableObject
{
    [ObservableProperty]
    private int speedCorrection;

    [ObservableProperty]
    private int successRate = 100;

    [ObservableProperty]
    private int repeatCount = 1;

    [ObservableProperty]
    private int tpGain;

    [ObservableProperty]
    private SkillHitType hitType = SkillHitType.Physical;

    [ObservableProperty]
    private int animationId;
}
