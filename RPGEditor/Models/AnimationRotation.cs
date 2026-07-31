using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class AnimationRotation : ObservableObject
{
    [ObservableProperty]
    private int x;

    [ObservableProperty]
    private int y;

    [ObservableProperty]
    private int z;
}
