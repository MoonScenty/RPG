using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class FlashTiming : ObservableObject
{
    [ObservableProperty]
    private int frame;

    [ObservableProperty]
    private int duration;

    /// <summary>[R, G, B, A], 각 0~255.</summary>
    public int[] Color { get; set; } = [255, 255, 255, 255];
}
