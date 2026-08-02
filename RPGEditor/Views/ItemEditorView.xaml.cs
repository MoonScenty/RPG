using System.Collections.Generic;
using System.Windows.Controls;
using RPGEditor.Models;

namespace RPGEditor.Views;

public partial class ItemEditorView : UserControl
{
    public string? ProjectRootPath
    {
        get => IconField.ProjectRootPath;
        set
        {
            IconField.ProjectRootPath = value;
            WeaponAnimationField.ProjectRootPath = value;
        }
    }

    /// <summary>속성/무기 유형/방어구 유형/장비 유형 콤보박스에 사용되는 Types.json 데이터.</summary>
    public TypesData? Types { get; set; }

    /// <summary>무기 공격 애니메이션 선택 다이얼로그에 사용되는 Animations.json 데이터.</summary>
    public IEnumerable<MvAnimationData>? Animations
    {
        get => WeaponAnimationField.Animations;
        set => WeaponAnimationField.Animations = value;
    }

    /// <summary>"사용 즉시 부여할 상태" 선택 다이얼로그에 사용되는 States.json 데이터.</summary>
    public IEnumerable<GameState>? States
    {
        get => ApplyStateField.States;
        set => ApplyStateField.States = value;
    }

    public ItemEditorView()
    {
        InitializeComponent();
    }
}
