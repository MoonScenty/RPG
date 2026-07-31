using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class EnemyMotionMapping : ObservableObject
{
    public string Motion { get; set; } = string.Empty;

    [ObservableProperty]
    private string animationName = string.Empty;
}
