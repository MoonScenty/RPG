using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class SoundTiming : ObservableObject
{
    [ObservableProperty]
    private int frame;

    public AnimationSoundEffect Se { get; set; } = new();
}
