using System.IO;
using System.Windows;
using System.Windows.Controls;

namespace RPGEditor.Views;

public partial class AudioField : UserControl
{
    public static readonly DependencyProperty FileNameProperty =
        DependencyProperty.Register(nameof(FileName), typeof(string), typeof(AudioField),
            new FrameworkPropertyMetadata(string.Empty, FrameworkPropertyMetadataOptions.BindsTwoWayByDefault));

    public static readonly DependencyProperty ProjectRootPathProperty =
        DependencyProperty.Register(nameof(ProjectRootPath), typeof(string), typeof(AudioField), new PropertyMetadata(null));

    /// <summary>ProjectRootPath 기준 상대 폴더 경로. 예: "audio/bgm".</summary>
    public static readonly DependencyProperty SubFolderProperty =
        DependencyProperty.Register(nameof(SubFolder), typeof(string), typeof(AudioField), new PropertyMetadata(string.Empty));

    public static readonly DependencyProperty PanProperty =
        DependencyProperty.Register(nameof(Pan), typeof(int), typeof(AudioField), new PropertyMetadata(0));

    public static readonly DependencyProperty PitchProperty =
        DependencyProperty.Register(nameof(Pitch), typeof(int), typeof(AudioField), new PropertyMetadata(100));

    public static readonly DependencyProperty VolumeProperty =
        DependencyProperty.Register(nameof(Volume), typeof(int), typeof(AudioField), new PropertyMetadata(90));

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

    public int Pan
    {
        get => (int)GetValue(PanProperty);
        set => SetValue(PanProperty, value);
    }

    public int Pitch
    {
        get => (int)GetValue(PitchProperty);
        set => SetValue(PitchProperty, value);
    }

    public int Volume
    {
        get => (int)GetValue(VolumeProperty);
        set => SetValue(VolumeProperty, value);
    }

    public AudioField()
    {
        InitializeComponent();
    }

    private void BrowseButton_Click(object sender, RoutedEventArgs e)
    {
        if (string.IsNullOrEmpty(ProjectRootPath) || string.IsNullOrEmpty(SubFolder))
            return;

        var folder = Path.Combine(ProjectRootPath, SubFolder);
        var dialog = new AudioPickerWindow(folder, FileName, Pan, Pitch, Volume)
        {
            Owner = Application.Current?.MainWindow,
        };
        if (dialog.ShowDialog() == true && dialog.SelectedFileName is { } name)
            FileName = name;
    }
}
