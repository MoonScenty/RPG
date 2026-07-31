using System.IO;
using System.Linq;
using System.Windows;
using System.Windows.Controls;

namespace RPGEditor.Views;

public partial class DragonBonesPickerWindow : Window
{
    public string? SelectedArmatureName { get; private set; }

    public DragonBonesPickerWindow(string dragonBonesFolderPath, string? currentArmatureName)
    {
        InitializeComponent();

        Preview.DragonBonesFolderPath = dragonBonesFolderPath;
        Preview.AnimationsLoaded += OnAnimationsLoaded;

        var names = Directory.Exists(dragonBonesFolderPath)
            ? Directory.GetFiles(dragonBonesFolderPath, "*_ske.json")
                .Select(f => Path.GetFileName(f)![..^"_ske.json".Length])
                .OrderBy(n => n, StringComparer.OrdinalIgnoreCase)
                .ToList()
            : [];

        ArmatureListBox.ItemsSource = names;
        if (!string.IsNullOrEmpty(currentArmatureName) && names.Contains(currentArmatureName))
            ArmatureListBox.SelectedItem = currentArmatureName;
        else if (names.Count > 0)
            ArmatureListBox.SelectedIndex = 0;
    }

    private void OnAnimationsLoaded(IReadOnlyList<string> names)
    {
        Dispatcher.Invoke(() =>
        {
            AnimationComboBox.ItemsSource = names;
            if (names.Count > 0)
                AnimationComboBox.SelectedIndex = 0;
            SelectButton.IsEnabled = ArmatureListBox.SelectedItem is not null;
        });
    }

    private async void ArmatureListBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
    {
        SelectButton.IsEnabled = false;
        AnimationComboBox.ItemsSource = null;

        if (ArmatureListBox.SelectedItem is string armatureName)
            await Preview.LoadArmatureAsync(armatureName);
    }

    private async void AnimationComboBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
    {
        if (AnimationComboBox.SelectedItem is string animationName)
            await Preview.PlayAnimationAsync(animationName);
    }

    private void SelectButton_Click(object sender, RoutedEventArgs e)
    {
        SelectedArmatureName = ArmatureListBox.SelectedItem as string;
        DialogResult = true;
    }
}
