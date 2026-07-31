using System.Windows.Controls;

namespace RPGEditor.Views;

public partial class StateEditorView : UserControl
{
    public string? ProjectRootPath
    {
        get => IconField.ProjectRootPath;
        set => IconField.ProjectRootPath = value;
    }

    public StateEditorView()
    {
        InitializeComponent();
    }
}
