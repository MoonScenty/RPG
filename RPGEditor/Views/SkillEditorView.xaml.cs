using System.Collections.Generic;
using System.Windows.Controls;
using RPGEditor.Models;

namespace RPGEditor.Views;

public partial class SkillEditorView : UserControl
{
    public string? ProjectRootPath
    {
        get => IconField.ProjectRootPath;
        set
        {
            IconField.ProjectRootPath = value;
            SkillAnimationField.ProjectRootPath = value;
        }
    }

    /// <summary>사용 효과의 상태 ID 콤보박스 + 요구 상태 선택 다이얼로그에 사용되는 States.json 데이터.</summary>
    public IEnumerable<GameState>? States
    {
        get => EffectsDataGrid.Tag as IEnumerable<GameState>;
        set
        {
            EffectsDataGrid.Tag = value;
            RequiredStateField.States = value;
            RequireTargetStateField.States = value;
            SelfHasStateField.States = value;
            SelfHasAppliesStateField.States = value;
            TargetHasStateField.States = value;
            TargetHasAppliesStateField.States = value;
            DamageBonusStateField.States = value;
        }
    }

    /// <summary>스킬 애니메이션 선택 다이얼로그에 사용되는 Animations.json 데이터.</summary>
    public IEnumerable<MvAnimationData>? Animations
    {
        get => SkillAnimationField.Animations;
        set => SkillAnimationField.Animations = value;
    }

    public SkillEditorView()
    {
        InitializeComponent();
    }
}
