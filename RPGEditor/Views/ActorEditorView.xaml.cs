using System.Collections.Generic;
using System.Windows.Controls;
using RPGEditor.Models;

namespace RPGEditor.Views;

public partial class ActorEditorView : UserControl
{
    public string? ProjectRootPath
    {
        get => BattlerField.ProjectRootPath;
        set
        {
            BattlerField.ProjectRootPath = value;
            FaceField.ProjectRootPath = value;
            HudFaceField.ProjectRootPath = value;
        }
    }

    /// <summary>직업 콤보박스에 사용되는 Classes.json 데이터.</summary>
    public IEnumerable<CharacterClass>? Classes { get; set; }

    /// <summary>장비 콤보박스에 사용되는 Items.json 데이터.</summary>
    public IEnumerable<Item>? Items
    {
        get => WeaponField.Items;
        set
        {
            WeaponField.Items = value;
            ShieldField.Items = value;
            HeadField.Items = value;
            BodyField.Items = value;
            AccessoryField.Items = value;
        }
    }

    public ActorEditorView()
    {
        InitializeComponent();
    }
}
