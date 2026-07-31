using System.IO;
using System.Windows;
using System.Windows.Controls;
using RPGEditor.Models;
using RPGEditor.ViewModels;

namespace RPGEditor.Views;

public partial class EnemyEditorView : UserControl
{
    public string? ProjectRootPath { get; set; }

    public EnemyEditorView()
    {
        InitializeComponent();
    }

    private void BrowseDragonBonesButton_Click(object sender, RoutedEventArgs e)
    {
        if (DataContext is not DatabaseListViewModel<Enemy> vm || vm.Selected is not { } enemy)
            return;
        if (string.IsNullOrEmpty(ProjectRootPath))
            return;

        var folder = Path.Combine(ProjectRootPath, "img", "dragonbones");
        var dialog = new DragonBonesPickerWindow(folder, enemy.Image)
        {
            Owner = Application.Current?.MainWindow,
        };
        if (dialog.ShowDialog() == true && dialog.SelectedArmatureName is { } name)
            enemy.Image = name;
    }
}
