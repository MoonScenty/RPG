using System.Windows;
using System.Windows.Controls;
using RPGEditor.ViewModels;

namespace RPGEditor.Views;

public partial class AnimationEditorView : UserControl
{
    public AnimationEditorView()
    {
        InitializeComponent();
    }

    /// <summary>스프라이트시트/오디오 미리보기를 위한 프로젝트 루트 경로.</summary>
    public string? ProjectRootPath
    {
        get => Preview.ProjectRootPath;
        set => Preview.ProjectRootPath = value;
    }

    private async void PlayButton_Click(object sender, RoutedEventArgs e)
    {
        if (DataContext is AnimationListViewModel vm)
            await Preview.PlayAsync(vm.Selected);
    }

    private async void StopButton_Click(object sender, RoutedEventArgs e)
    {
        await Preview.StopAsync();
    }
}
