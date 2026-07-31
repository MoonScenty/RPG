using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class ClassLearning : ObservableObject
{
    [ObservableProperty]
    private int level;

    [ObservableProperty]
    private int skillId;
}
