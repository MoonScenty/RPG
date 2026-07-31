using System.Windows.Controls;

namespace RPGEditor.Views;

public partial class SkillEditorView : UserControl
{
    public string? ProjectRootPath
    {
        get => IconField.ProjectRootPath;
        set => IconField.ProjectRootPath = value;
    }

    public SkillEditorView()
    {
        InitializeComponent();
    }
}
