using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RPGEditor.Models;

namespace RPGEditor.Views;

public partial class StatePickerWindow : Window
{
    private sealed record StateOption(int? Id, string Display);

    private readonly List<StateOption> _options;

    public int? SelectedStateId { get; private set; }

    public StatePickerWindow(IEnumerable<GameState> states, int? currentId)
    {
        InitializeComponent();

        _options = new List<StateOption> { new(null, "(없음)") };
        _options.AddRange(states.Select(s => new StateOption(s.Id, $"{s.Id}: {s.Name}")));
        StateListBox.ItemsSource = _options;

        var current = _options.FirstOrDefault(o => o.Id == currentId);
        if (current is not null)
        {
            StateListBox.SelectedItem = current;
            StateListBox.ScrollIntoView(current);
        }
    }

    private void StateListBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
    {
        SelectButton.IsEnabled = StateListBox.SelectedItem is not null;
    }

    private void SelectButton_Click(object sender, RoutedEventArgs e)
    {
        if (StateListBox.SelectedItem is not StateOption option)
            return;

        SelectedStateId = option.Id;
        DialogResult = true;
    }
}
