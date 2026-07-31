using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class DatabaseEntry : ObservableObject, IDatabaseEntry
{
    [ObservableProperty]
    private int id;

    [ObservableProperty]
    private string name = string.Empty;

    [ObservableProperty]
    private string note = string.Empty;
}
