using System.Collections.Generic;
using System.Windows.Controls;
using RPGEditor.Models;

namespace RPGEditor.Views;

public partial class SkillEditorView : UserControl
{
    public string? ProjectRootPath
    {
        get => IconField.ProjectRootPath;
        set => IconField.ProjectRootPath = value;
    }

    /// <summary>사용 효과의 상태 ID 콤보박스에 사용되는 States.json 데이터.</summary>
    public IEnumerable<GameState>? States
    {
        get => EffectsDataGrid.Tag as IEnumerable<GameState>;
        set => EffectsDataGrid.Tag = value;
    }

    public SkillEditorView()
    {
        InitializeComponent();
    }
}
