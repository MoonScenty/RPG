using System.IO;
using System.Windows;
using System.Windows.Controls;
using System.Windows.Media.Imaging;

namespace RPGEditor.Views;

public partial class ImageField : UserControl
{
    public static readonly DependencyProperty FileNameProperty =
        DependencyProperty.Register(nameof(FileName), typeof(string), typeof(ImageField),
            new FrameworkPropertyMetadata(string.Empty, FrameworkPropertyMetadataOptions.BindsTwoWayByDefault, OnChanged));

    public static readonly DependencyProperty ProjectRootPathProperty =
        DependencyProperty.Register(nameof(ProjectRootPath), typeof(string), typeof(ImageField),
            new PropertyMetadata(null, OnChanged));

    /// <summary>ProjectRootPath 기준 상대 폴더 경로. 예: "img/battlebacks1".</summary>
    public static readonly DependencyProperty SubFolderProperty =
        DependencyProperty.Register(nameof(SubFolder), typeof(string), typeof(ImageField),
            new PropertyMetadata(string.Empty, OnChanged));

    public static readonly DependencyProperty PickerTitleProperty =
        DependencyProperty.Register(nameof(PickerTitle), typeof(string), typeof(ImageField), new PropertyMetadata("이미지 선택"));

    public string FileName
    {
        get => (string)GetValue(FileNameProperty);
        set => SetValue(FileNameProperty, value);
    }

    public string? ProjectRootPath
    {
        get => (string?)GetValue(ProjectRootPathProperty);
        set => SetValue(ProjectRootPathProperty, value);
    }

    public string SubFolder
    {
        get => (string)GetValue(SubFolderProperty);
        set => SetValue(SubFolderProperty, value);
    }

    public string PickerTitle
    {
        get => (string)GetValue(PickerTitleProperty);
        set => SetValue(PickerTitleProperty, value);
    }

    public ImageField()
    {
        InitializeComponent();
    }

    private static void OnChanged(DependencyObject d, DependencyPropertyChangedEventArgs e)
        => ((ImageField)d).UpdatePreview();

    private string? FolderPath =>
        string.IsNullOrEmpty(ProjectRootPath) || string.IsNullOrEmpty(SubFolder)
            ? null
            : Path.Combine(ProjectRootPath, SubFolder);

    private void UpdatePreview()
    {
        var folder = FolderPath;
        if (folder is null || string.IsNullOrEmpty(FileName))
        {
            PreviewImage.Source = null;
            return;
        }

        var path = Path.Combine(folder, FileName + ".png");
        if (!File.Exists(path))
        {
            PreviewImage.Source = null;
            return;
        }

        var bmp = new BitmapImage();
        bmp.BeginInit();
        bmp.CacheOption = BitmapCacheOption.OnLoad;
        bmp.UriSource = new Uri(path, UriKind.Absolute);
        bmp.EndInit();
        bmp.Freeze();
        PreviewImage.Source = bmp;
    }

    private void BrowseButton_Click(object sender, RoutedEventArgs e)
    {
        var folder = FolderPath;
        if (folder is null)
            return;

        var dialog = new ImageFilePickerWindow(folder, FileName, PickerTitle)
        {
            Owner = Application.Current?.MainWindow,
        };
        if (dialog.ShowDialog() == true && dialog.SelectedImageName is { } name)
            FileName = name;
    }
}
