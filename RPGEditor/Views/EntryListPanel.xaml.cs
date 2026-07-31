using System.Windows;
using System.Windows.Controls;

namespace RPGEditor.Views;

public partial class EntryListPanel : UserControl
{
    public static readonly DependencyProperty ShowIdProperty =
        DependencyProperty.Register(nameof(ShowId), typeof(bool), typeof(EntryListPanel), new PropertyMetadata(false));

    /// <summary>true면 목록 각 항목 앞에 ID를 함께 표시한다.</summary>
    public bool ShowId
    {
        get => (bool)GetValue(ShowIdProperty);
        set => SetValue(ShowIdProperty, value);
    }

    public EntryListPanel()
    {
        InitializeComponent();
    }
}
