using System.Linq;
using System.Windows;
using System.Windows.Controls;
using RPGEditor.Models;

namespace RPGEditor.Views;

public partial class AnimationPickerWindow : Window
{
    private sealed record AnimationOption(int Id, string Display, MvAnimationData Animation);

    private readonly List<AnimationOption> _options;

    public int SelectedAnimationId { get; private set; }

    public AnimationPickerWindow(IEnumerable<MvAnimationData> animations, int currentId, string? projectRootPath)
    {
        InitializeComponent();

        Preview.ProjectRootPath = projectRootPath;

        _options = animations.Select(a => new AnimationOption(a.Id, $"{a.Id}: {a.Name}", a)).ToList();
        AnimationListBox.ItemsSource = _options;

        var current = _options.FirstOrDefault(o => o.Id == currentId);
        if (current is not null)
        {
            AnimationListBox.SelectedItem = current;
            AnimationListBox.ScrollIntoView(current);
        }
    }

    private async void AnimationListBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
    {
        SelectButton.IsEnabled = AnimationListBox.SelectedItem is not null;
        if (AnimationListBox.SelectedItem is AnimationOption option)
            await Preview.PlayAsync(option.Animation);
    }

    private async void SelectButton_Click(object sender, RoutedEventArgs e)
    {
        if (AnimationListBox.SelectedItem is not AnimationOption option)
            return;

        await Preview.StopAsync();
        SelectedAnimationId = option.Id;
        DialogResult = true;
    }
}
