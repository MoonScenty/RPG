using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class AnimationSoundEffect : ObservableObject
{
    /// <summary>audio/se 참조.</summary>
    [ObservableProperty]
    private string name = string.Empty;

    [ObservableProperty]
    private int pan;

    [ObservableProperty]
    private int pitch = 100;

    [ObservableProperty]
    private int volume = 90;
}
