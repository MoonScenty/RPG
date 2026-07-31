using System.IO;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media.Imaging;

namespace RPGEditor.Views;

public partial class IconField : UserControl
{
    public static readonly DependencyProperty IconIndexProperty =
        DependencyProperty.Register(nameof(IconIndex), typeof(int), typeof(IconField),
            new FrameworkPropertyMetadata(0, FrameworkPropertyMetadataOptions.BindsTwoWayByDefault, OnIconChanged));

    public static readonly DependencyProperty ProjectRootPathProperty =
        DependencyProperty.Register(nameof(ProjectRootPath), typeof(string), typeof(IconField),
            new PropertyMetadata(null, OnIconChanged));

    public int IconIndex
    {
        get => (int)GetValue(IconIndexProperty);
        set => SetValue(IconIndexProperty, value);
    }

    public string? ProjectRootPath
    {
        get => (string?)GetValue(ProjectRootPathProperty);
        set => SetValue(ProjectRootPathProperty, value);
    }

    public IconField()
    {
        InitializeComponent();
    }

    private static void OnIconChanged(DependencyObject d, DependencyPropertyChangedEventArgs e)
        => ((IconField)d).UpdatePreview();

    private void UpdatePreview()
    {
        PreviewImage.Source = LoadIcon(ProjectRootPath, IconIndex);
    }

    internal static BitmapImage? LoadIcon(string? projectRootPath, int index)
    {
        if (string.IsNullOrEmpty(projectRootPath))
            return null;

        var path = Path.Combine(projectRootPath, "img", "icons", $"{index}.png");
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

    private void BrowseButton_Click(object sender, RoutedEventArgs e)
    {
        if (string.IsNullOrEmpty(ProjectRootPath))
            return;

        var folder = Path.Combine(ProjectRootPath, "img", "icons");
        var dialog = new IconPickerWindow(folder, IconIndex)
        {
            Owner = Application.Current?.MainWindow,
        };
        if (dialog.ShowDialog() == true)
            IconIndex = dialog.SelectedIconIndex;
    }
}
