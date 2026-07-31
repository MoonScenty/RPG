using System.Collections.Generic;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RPGEditor.Models;

namespace RPGEditor.Views;

public partial class EnemySlotField : UserControl
{
    public static readonly DependencyProperty EnemyIdProperty =
        DependencyProperty.Register(nameof(EnemyId), typeof(int?), typeof(EnemySlotField),
            new FrameworkPropertyMetadata(null, FrameworkPropertyMetadataOptions.BindsTwoWayByDefault, OnChanged));

    public static readonly DependencyProperty EnemiesProperty =
        DependencyProperty.Register(nameof(Enemies), typeof(IEnumerable<Enemy>), typeof(EnemySlotField),
            new PropertyMetadata(null, OnChanged));

    public int? EnemyId
    {
        get => (int?)GetValue(EnemyIdProperty);
        set => SetValue(EnemyIdProperty, value);
    }

    public IEnumerable<Enemy>? Enemies
    {
        get => (IEnumerable<Enemy>?)GetValue(EnemiesProperty);
        set => SetValue(EnemiesProperty, value);
    }

    public EnemySlotField()
    {
        InitializeComponent();
    }

    private static void OnChanged(DependencyObject d, DependencyPropertyChangedEventArgs e)
        => ((EnemySlotField)d).UpdateDisplay();

    private void UpdateDisplay()
    {
        var id = EnemyId;
        var enemy = id is null ? null : Enemies?.FirstOrDefault(en => en.Id == id);
        DisplayText.Text = enemy is not null ? $"{enemy.Id}: {enemy.Name}" : "(없음)";
    }

    private void SelectButton_Click(object sender, RoutedEventArgs e)
    {
        var dialog = new EnemyPickerWindow(Enemies ?? [], EnemyId)
        {
            Owner = Application.Current?.MainWindow,
        };
        if (dialog.ShowDialog() == true)
            EnemyId = dialog.SelectedEnemyId;
    }
}
