using System.Windows.Controls;

namespace RPGEditor.Views;

public partial class ItemEditorView : UserControl
{
    public string? ProjectRootPath
    {
        get => IconField.ProjectRootPath;
        set => IconField.ProjectRootPath = value;
    }

    public ItemEditorView()
    {
        InitializeComponent();
    }
}
