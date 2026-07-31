using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class EntryType : DatabaseEntry
{
    [ObservableProperty]
    private TypeGroup group = TypeGroup.Element;
}
