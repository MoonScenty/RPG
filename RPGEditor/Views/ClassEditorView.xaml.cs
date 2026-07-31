using System.Windows.Controls;

namespace RPGEditor.Views;

public partial class ClassEditorView : UserControl
{
    public string? ProjectRootPath
    {
        get => IconField.ProjectRootPath;
        set => IconField.ProjectRootPath = value;
    }

    public ClassEditorView()
    {
        InitializeComponent();
    }
}
