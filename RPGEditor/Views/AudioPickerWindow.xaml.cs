using System.IO;
using System.Linq;
using System.Windows;
using System.Windows.Controls;

namespace RPGEditor.Views;

public partial class AudioPickerWindow : Window
{
    private readonly int _pan;
    private readonly int _pitch;
    private readonly int _volume;

    public string? SelectedFileName { get; private set; }

    public AudioPickerWindow(string folderPath, string? currentFileName, int pan, int pitch, int volume)
    {
        InitializeComponent();
        _pan = pan;
        _pitch = pitch;
        _volume = volume;

        Preview.AudioFolderPath = folderPath;
        Preview.StatusChanged += text => Dispatcher.Invoke(() => StatusText.Text = text);

        var names = Directory.Exists(folderPath)
            ? Directory.GetFiles(folderPath, "*.ogg")
                .Select(f => Path.GetFileNameWithoutExtension(f))
                .OrderBy(n => n, StringComparer.OrdinalIgnoreCase)
                .ToList()
            : [];

        AudioListBox.ItemsSource = names;
        if (!string.IsNullOrEmpty(currentFileName) && names.Contains(currentFileName))
            AudioListBox.SelectedItem = currentFileName;
        else if (names.Count > 0)
            AudioListBox.SelectedIndex = 0;

        if (AudioListBox.SelectedItem is not null)
            AudioListBox.ScrollIntoView(AudioListBox.SelectedItem);
    }

    private async void AudioListBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
    {
        SelectButton.IsEnabled = AudioListBox.SelectedItem is not null;
        if (AudioListBox.SelectedItem is string name)
            await Preview.PlayAsync(name, _pan, _pitch, _volume);
    }

    private async void PlayButton_Click(object sender, RoutedEventArgs e)
    {
        if (AudioListBox.SelectedItem is string name)
            await Preview.PlayAsync(name, _pan, _pitch, _volume);
    }

    private async void StopButton_Click(object sender, RoutedEventArgs e)
    {
        await Preview.StopAsync();
    }

    private async void ClearButton_Click(object sender, RoutedEventArgs e)
    {
        await Preview.StopAsync();
        SelectedFileName = string.Empty;
        DialogResult = true;
    }

    private async void SelectButton_Click(object sender, RoutedEventArgs e)
    {
        if (AudioListBox.SelectedItem is not string name)
            return;

        await Preview.StopAsync();
        SelectedFileName = name;
        DialogResult = true;
    }
}
