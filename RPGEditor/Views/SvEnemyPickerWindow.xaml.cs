using System.IO;
using System.Linq;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media.Imaging;

namespace RPGEditor.Views;

public partial class SvEnemyPickerWindow : Window
{
    private readonly string _folderPath;

    public string? SelectedImageName { get; private set; }

    public SvEnemyPickerWindow(string folderPath, string? currentImageName)
    {
        InitializeComponent();
        _folderPath = folderPath;

        var names = Directory.Exists(folderPath)
            ? Directory.GetFiles(folderPath, "*.png")
                .Select(f => Path.GetFileNameWithoutExtension(f))
                .OrderBy(n => n, StringComparer.OrdinalIgnoreCase)
                .ToList()
            : [];

        ImageListBox.ItemsSource = names;
        if (!string.IsNullOrEmpty(currentImageName) && names.Contains(currentImageName))
            ImageListBox.SelectedItem = currentImageName;
        else if (names.Count > 0)
            ImageListBox.SelectedIndex = 0;

        if (ImageListBox.SelectedItem is not null)
            ImageListBox.ScrollIntoView(ImageListBox.SelectedItem);
    }

    private void ImageListBox_SelectionChanged(object sender, SelectionChangedEventArgs e)
    {
        if (ImageListBox.SelectedItem is not string name)
        {
            PreviewImage.Source = null;
            SelectButton.IsEnabled = false;
            return;
        }

        var path = Path.Combine(_folderPath, name + ".png");
        var bitmap = new BitmapImage();
        bitmap.BeginInit();
        bitmap.CacheOption = BitmapCacheOption.OnLoad;
        bitmap.UriSource = new Uri(path, UriKind.Absolute);
        bitmap.EndInit();
        PreviewImage.Source = bitmap;
        SelectButton.IsEnabled = true;
    }

    private void SelectButton_Click(object sender, RoutedEventArgs e)
    {
        SelectedImageName = ImageListBox.SelectedItem as string;
        DialogResult = true;
    }
}
