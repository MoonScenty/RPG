using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RPGEditor.Models;

namespace RPGEditor.Views;

public partial class StateField : UserControl
{
    public static readonly DependencyProperty StateIdProperty =
        DependencyProperty.Register(nameof(StateId), typeof(int?), typeof(StateField),
            new FrameworkPropertyMetadata(null, FrameworkPropertyMetadataOptions.BindsTwoWayByDefault, OnChanged));

    public static readonly DependencyProperty StatesProperty =
        DependencyProperty.Register(nameof(States), typeof(IEnumerable<GameState>), typeof(StateField),
            new PropertyMetadata(null, OnChanged));

    public int? StateId
    {
        get => (int?)GetValue(StateIdProperty);
        set => SetValue(StateIdProperty, value);
    }

    public IEnumerable<GameState>? States
    {
        get => (IEnumerable<GameState>?)GetValue(StatesProperty);
        set => SetValue(StatesProperty, value);
    }

    public StateField()
    {
        InitializeComponent();
    }

    private static void OnChanged(DependencyObject d, DependencyPropertyChangedEventArgs e)
        => ((StateField)d).UpdateDisplay();

    private void UpdateDisplay()
    {
        var state = States?.FirstOrDefault(s => s.Id == StateId);
        DisplayText.Text = state is not null ? $"{state.Id}: {state.Name}" : "(없음)";
    }

    private void SelectButton_Click(object sender, RoutedEventArgs e)
    {
        var dialog = new StatePickerWindow(States ?? [], StateId)
        {
            Owner = Application.Current?.MainWindow,
        };
        if (dialog.ShowDialog() == true)
            StateId = dialog.SelectedStateId;
    }
}
