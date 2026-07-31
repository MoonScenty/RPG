using System.IO;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media.Imaging;

namespace RPGEditor.Views;

public partial class IconPickerWindow : Window
{
    private sealed record IconEntry(int Index, BitmapImage? Image);

    private readonly List<IconEntry> _allIcons;

    public int SelectedIconIndex { get; private set; }

    public IconPickerWindow(string iconsFolderPath, int currentIndex)
    {
        InitializeComponent();

        _allIcons = Directory.Exists(iconsFolderPath)
            ? Directory.GetFiles(iconsFolderPath, "*.png")
                .Select(f => Path.GetFileNameWithoutExtension(f))
                .Where(n => int.TryParse(n, out _))
                .Select(int.Parse)
                .OrderBy(i => i)
                .Select(i => new IconEntry(i, LoadIcon(iconsFolderPath, i)))
                .ToList()
            : [];

        IconListBox.ItemsSource = _allIcons;

        var current = _allIcons.FirstOrDefault(entry => entry.Index == currentIndex);
        if (current is not null)
        {
            IconListBox.SelectedItem = current;
            IconListBox.ScrollIntoView(current);
        }
    }

    private static BitmapImage? LoadIcon(string folder, int index)
    {
        var path = Path.Combine(folder, $"{index}.png");
        if (!File.Exists(path))
            return null;

        var bmp = new BitmapImage();
        bmp.BeginInit();
        bmp.CacheOption = BitmapCacheOption.OnLoad;
        bmp.UriSource = new Uri(path, UriKind.Absolute);
        bmp.EndInit();
        bmp.Freeze();
        return bmp;
    }

    private void FilterTextBox_TextChanged(object sender, TextChangedEventArgs e)
    {
        var text = FilterTextBox.Text.Trim();
        IconListBox.ItemsSource = string.IsNullOrEmpty(text)
            ? _allIcons
            : _allIcons.Where(entry => entry.Index.ToString().Contains(text)).ToList();
    }

    private void IconListBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
    {
        SelectButton.IsEnabled = IconListBox.SelectedItem is not null;
        SelectedIndexText.Text = IconListBox.SelectedItem is IconEntry entry ? $"선택: {entry.Index}" : string.Empty;
    }

    private void SelectButton_Click(object sender, RoutedEventArgs e)
    {
        if (IconListBox.SelectedItem is not IconEntry entry)
            return;

        SelectedIconIndex = entry.Index;
        DialogResult = true;
    }
}
