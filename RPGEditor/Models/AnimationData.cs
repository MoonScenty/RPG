using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class AnimationData : DatabaseEntry
{
    [ObservableProperty]
    private AnimationDisplayType displayType = AnimationDisplayType.OnEachTarget;

    /// <summary>effects 폴더의 Effekseer(.efkefc) 이펙트 이름.</summary>
    [ObservableProperty]
    private string effectName = string.Empty;

    public ObservableCollection<FlashTiming> FlashTimings { get; set; } = [];

    [ObservableProperty]
    private int offsetX;

    [ObservableProperty]
    private int offsetY;

    public AnimationRotation Rotation { get; set; } = new();

    [ObservableProperty]
    private int scale = 100;

    public ObservableCollection<SoundTiming> SoundTimings { get; set; } = [];

    [ObservableProperty]
    private int speed = 100;

    public List<object> Timings { get; set; } = [];
}
