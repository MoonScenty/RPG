using System.IO;
using System.Windows;
using Microsoft.Win32;
using RPGEditor.Project;

namespace RPGEditor.Views;

public partial class NewProjectWindow : Window
{
    public ProjectContext? CreatedProject { get; private set; }

    public NewProjectWindow()
    {
        InitializeComponent();
    }

    private void BrowseButton_Click(object sender, RoutedEventArgs e)
    {
        var dialog = new OpenFolderDialog { Title = "프로젝트 생성 위치 선택" };
        if (dialog.ShowDialog(this) == true)
        {
            LocationTextBox.Text = dialog.FolderName;
        }
    }

    private void CreateButton_Click(object sender, RoutedEventArgs e)
    {
        var projectName = ProjectNameTextBox.Text.Trim();
        var location = LocationTextBox.Text.Trim();

        if (string.IsNullOrWhiteSpace(projectName))
        {
            ErrorTextBlock.Text = "프로젝트 이름을 입력하세요.";
            return;
        }

        if (projectName.IndexOfAny(Path.GetInvalidFileNameChars()) >= 0)
        {
            ErrorTextBlock.Text = "프로젝트 이름에 사용할 수 없는 문자가 포함되어 있습니다.";
            return;
        }

        if (string.IsNullOrWhiteSpace(location) || !Directory.Exists(location))
        {
            ErrorTextBlock.Text = "생성 위치를 선택하세요.";
            return;
        }

        if (Directory.Exists(Path.Combine(location, projectName)))
        {
            ErrorTextBlock.Text = "같은 이름의 폴더가 이미 존재합니다.";
            return;
        }

        CreatedProject = ProjectContext.CreateNew(location, projectName);
        DialogResult = true;
    }
}
