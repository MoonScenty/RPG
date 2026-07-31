using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class SkillDamage : ObservableObject
{
    [ObservableProperty]
    private DamageType type = DamageType.None;

    /// <summary>속성 ID. 0 = 무속성. Types.json 참조.</summary>
    [ObservableProperty]
    private int elementId;

    [ObservableProperty]
    private string formula = "0";

    [ObservableProperty]
    private int variance = 20;

    [ObservableProperty]
    private bool criticalEnabled;
}
