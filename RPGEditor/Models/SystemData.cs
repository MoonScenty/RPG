using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class SystemData : ObservableObject
{
    [ObservableProperty]
    private string gameTitle = string.Empty;

    [ObservableProperty]
    private int windowWidth = 816;

    [ObservableProperty]
    private int windowHeight = 624;

    [ObservableProperty]
    private string note = string.Empty;
}
