using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class ExpCurve : ObservableObject
{
    [ObservableProperty]
    private int basis = 30;

    [ObservableProperty]
    private int extra = 20;

    [ObservableProperty]
    private int accelerationA = 30;

    [ObservableProperty]
    private int accelerationB = 30;
}
