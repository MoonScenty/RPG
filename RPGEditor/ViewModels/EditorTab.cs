using System.Windows;

namespace RPGEditor.ViewModels;

public class EditorTab
{
    public required string Header { get; init; }
    public required FrameworkElement Content { get; init; }
}
