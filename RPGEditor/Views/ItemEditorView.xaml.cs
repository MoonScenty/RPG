using System.Windows.Controls;
using RPGEditor.Models;

namespace RPGEditor.Views;

public partial class ItemEditorView : UserControl
{
    public string? ProjectRootPath
    {
        get => IconField.ProjectRootPath;
        set => IconField.ProjectRootPath = value;
    }

    /// <summary>속성/무기 유형/방어구 유형/장비 유형 콤보박스에 사용되는 Types.json 데이터.</summary>
    public TypesData? Types { get; set; }

    public ItemEditorView()
    {
        InitializeComponent();
    }
}
