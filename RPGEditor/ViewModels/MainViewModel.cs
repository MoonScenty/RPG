using System.Collections.ObjectModel;
using System.Windows;
using CommunityToolkit.Mvvm.ComponentModel;
using CommunityToolkit.Mvvm.Input;
using Microsoft.Win32;
using RPGEditor.Models;
using RPGEditor.Project;
using RPGEditor.Views;

namespace RPGEditor.ViewModels;

public partial class MainViewModel : ObservableObject
{
    [ObservableProperty]
    [NotifyPropertyChangedFor(nameof(HasProject))]
    [NotifyCanExecuteChangedFor(nameof(SaveProjectCommand))]
    private ProjectContext? project;

    [ObservableProperty]
    private string statusText = "프로젝트가 열려 있지 않습니다.";

    public ObservableCollection<EditorTab> Tabs { get; } = [];

    public bool HasProject => Project is not null;

    [RelayCommand]
    private void NewProject(Window owner)
    {
        var dialog = new NewProjectWindow { Owner = owner };
        if (dialog.ShowDialog() == true && dialog.CreatedProject is not null)
        {
            LoadProject(dialog.CreatedProject);
        }
    }

    [RelayCommand]
    private void OpenProject(Window owner)
    {
        var dialog = new OpenFileDialog
        {
            Filter = "RPG 프로젝트 파일 (*.rpgprj)|*.rpgprj",
            Title = "프로젝트 열기",
        };

        if (dialog.ShowDialog(owner) != true)
            return;

        try
        {
            LoadProject(ProjectContext.Load(dialog.FileName));
        }
        catch (Exception ex)
        {
            MessageBox.Show(owner, $"프로젝트를 여는 중 오류가 발생했습니다.\n{ex.Message}", "오류",
                MessageBoxButton.OK, MessageBoxImage.Error);
        }
    }

    [RelayCommand(CanExecute = nameof(HasProject))]
    private void SaveProject()
    {
        if (Project is null)
            return;

        Project.Save();
        StatusText = $"저장됨: {Project.ProjectFilePath}";
    }

    [RelayCommand]
    private static void Exit()
    {
        Application.Current.Shutdown();
    }

    private void LoadProject(ProjectContext context)
    {
        Project = context;
        StatusText = $"프로젝트: {context.ProjectFilePath}";

        Tabs.Clear();
        Tabs.Add(new EditorTab
        {
            Header = "액터",
            Content = new ActorEditorView { DataContext = new DatabaseListViewModel<Actor>("액터", context.Actors) },
        });
        Tabs.Add(new EditorTab
        {
            Header = "직업",
            Content = new ClassEditorView
            {
                DataContext = new DatabaseListViewModel<CharacterClass>("직업", context.Classes),
                ProjectRootPath = context.ProjectRootPath,
            },
        });
        Tabs.Add(new EditorTab
        {
            Header = "스킬",
            Content = new SkillEditorView
            {
                DataContext = new DatabaseListViewModel<Skill>("스킬", context.Skills),
                ProjectRootPath = context.ProjectRootPath,
            },
        });
        Tabs.Add(new EditorTab
        {
            Header = "아이템",
            Content = new ItemEditorView
            {
                DataContext = new DatabaseListViewModel<Item>("아이템", context.Items),
                ProjectRootPath = context.ProjectRootPath,
            },
        });
        Tabs.Add(new EditorTab
        {
            Header = "적 캐릭터",
            Content = new EnemyEditorView
            {
                DataContext = new DatabaseListViewModel<Enemy>("적 캐릭터", context.Enemies),
                ProjectRootPath = context.ProjectRootPath,
            },
        });
        Tabs.Add(new EditorTab
        {
            Header = "적 군단",
            Content = new TroopEditorView { DataContext = new DatabaseListViewModel<Troop>("적 군단", context.Troops) },
        });
        Tabs.Add(new EditorTab
        {
            Header = "상태",
            Content = new StateEditorView
            {
                DataContext = new DatabaseListViewModel<GameState>("상태", context.States),
                ProjectRootPath = context.ProjectRootPath,
            },
        });
        Tabs.Add(new EditorTab
        {
            Header = "애니메이션",
            Content = new AnimationEditorView
            {
                DataContext = new DatabaseListViewModel<AnimationData>("애니메이션", context.Animations),
                ProjectRootPath = context.ProjectRootPath,
            },
        });
        Tabs.Add(new EditorTab
        {
            Header = "유형",
            Content = new TypesEditorView { DataContext = new TypesEditorViewModel(context.Types) },
        });
    }

    private static EditorTab CreateTab<T>(string header, ObservableCollection<T> entries)
        where T : DatabaseEntry, new()
    {
        return new EditorTab
        {
            Header = header,
            Content = new DatabaseEditorView { DataContext = new DatabaseListViewModel<T>(header, entries) },
        };
    }
}
