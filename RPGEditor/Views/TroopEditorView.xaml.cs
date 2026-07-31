using System.Collections.Generic;
using System.Windows.Controls;
using RPGEditor.Models;

namespace RPGEditor.Views;

public partial class TroopEditorView : UserControl
{
    public string? ProjectRootPath
    {
        get => Battleback1Field.ProjectRootPath;
        set
        {
            Battleback1Field.ProjectRootPath = value;
            Battleback2Field.ProjectRootPath = value;
            BattleBgmField.ProjectRootPath = value;
            VictoryMeField.ProjectRootPath = value;
            DefeatMeField.ProjectRootPath = value;
        }
    }

    public IEnumerable<Enemy>? Enemies
    {
        get => FrontTopField.Enemies;
        set
        {
            FrontTopField.Enemies = value;
            FrontMiddleField.Enemies = value;
            FrontBottomField.Enemies = value;
            BackTopField.Enemies = value;
            BackMiddleField.Enemies = value;
            BackBottomField.Enemies = value;
        }
    }

    public TroopEditorView()
    {
        InitializeComponent();
    }
}
