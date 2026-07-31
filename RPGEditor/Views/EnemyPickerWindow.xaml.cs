using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RPGEditor.Models;

namespace RPGEditor.Views;

public partial class EnemyPickerWindow : Window
{
    private sealed record EnemyOption(int Id, string Display);

    public int? SelectedEnemyId { get; private set; }

    public EnemyPickerWindow(IEnumerable<Enemy> enemies, int? currentEnemyId)
    {
        InitializeComponent();

        var options = enemies.Select(e => new EnemyOption(e.Id, $"{e.Id}: {e.Name}")).ToList();
        EnemyListBox.ItemsSource = options;

        var current = options.FirstOrDefault(o => o.Id == currentEnemyId);
        if (current is not null)
        {
            EnemyListBox.SelectedItem = current;
            EnemyListBox.ScrollIntoView(current);
        }
    }

    private void EnemyListBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
    {
        SelectButton.IsEnabled = EnemyListBox.SelectedItem is not null;
    }

    private void ClearButton_Click(object sender, RoutedEventArgs e)
    {
        SelectedEnemyId = null;
        DialogResult = true;
    }

    private void SelectButton_Click(object sender, RoutedEventArgs e)
    {
        if (EnemyListBox.SelectedItem is not EnemyOption option)
            return;

        SelectedEnemyId = option.Id;
        DialogResult = true;
    }
}
