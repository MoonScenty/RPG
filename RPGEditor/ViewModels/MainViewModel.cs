using System.Collections.ObjectModel;
using System.IO;
using System.Windows;
using System.Windows.Threading;
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

    // data/*.json이 git pull 등으로 외부에서 바뀌면 재로드를 물어본다. 여러 파일이
    // 한꺼번에 바뀌어도(예: git checkout) 이벤트가 파일마다 따로 들어오니, 짧은
    // 디바운스로 묶어서 대화상자를 한 번만 띄운다. SaveProject()가 쓰는 동안은
    // 자기 자신의 저장을 외부 변경으로 오인하지 않게 잠깐 꺼둔다.
    private FileSystemWatcher? projectFilesWatcher;
    private DispatcherTimer? reloadPromptDebounceTimer;
    private bool reloadPromptOpen;

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

        // 저장 중 발생하는 파일 변경 이벤트는 워처가 그대로 감지해버리므로, 쓰는
        // 동안은 꺼뒀다가 알림이 뒤늦게 도착할 여유를 두고 다시 켠다.
        if (projectFilesWatcher is not null)
            projectFilesWatcher.EnableRaisingEvents = false;

        Project.Save();
        StatusText = $"저장됨: {Project.ProjectFilePath}";

        var watcher = projectFilesWatcher;
        if (watcher is not null)
        {
            var resumeTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(500) };
            resumeTimer.Tick += (_, _) =>
            {
                resumeTimer.Stop();
                if (projectFilesWatcher == watcher)
                    watcher.EnableRaisingEvents = true;
            };
            resumeTimer.Start();
        }
    }

    partial void OnProjectChanged(ProjectContext? value)
    {
        projectFilesWatcher?.Dispose();
        projectFilesWatcher = null;
        reloadPromptDebounceTimer?.Stop();
        reloadPromptDebounceTimer = null;

        if (value is null)
            return;

        var dataDir = Path.Combine(value.ProjectRootPath, "data");
        if (!Directory.Exists(dataDir))
            return;

        var watcher = new FileSystemWatcher(dataDir, "*.json")
        {
            NotifyFilter = NotifyFilters.LastWrite | NotifyFilters.Size | NotifyFilters.FileName,
        };
        watcher.Changed += OnProjectDataFileChangedExternally;
        watcher.Created += OnProjectDataFileChangedExternally;
        watcher.Renamed += OnProjectDataFileChangedExternally;
        watcher.EnableRaisingEvents = true;
        projectFilesWatcher = watcher;
    }

    private void OnProjectDataFileChangedExternally(object sender, FileSystemEventArgs e)
    {
        // FileSystemWatcher 콜백은 스레드풀 스레드에서 오므로 UI 스레드로 넘겨야 한다.
        Application.Current.Dispatcher.BeginInvoke(() =>
        {
            if (reloadPromptOpen || Project is null)
                return;

            reloadPromptDebounceTimer?.Stop();
            reloadPromptDebounceTimer = new DispatcherTimer { Interval = TimeSpan.FromMilliseconds(400) };
            reloadPromptDebounceTimer.Tick += (_, _) =>
            {
                reloadPromptDebounceTimer!.Stop();
                PromptReloadFromDisk();
            };
            reloadPromptDebounceTimer.Start();
        });
    }

    private void PromptReloadFromDisk()
    {
        if (Project is null)
            return;

        reloadPromptOpen = true;
        try
        {
            var owner = Application.Current.MainWindow;
            var result = MessageBox.Show(owner,
                "외부에서 파일 변경을 감지했습니다. 다시 불러올까요?", "변경 감지",
                MessageBoxButton.OKCancel, MessageBoxImage.Question);

            if (result != MessageBoxResult.OK)
                return;

            try
            {
                LoadProject(ProjectContext.Load(Project.ProjectFilePath));
            }
            catch (Exception ex)
            {
                MessageBox.Show(owner, $"프로젝트를 다시 불러오는 중 오류가 발생했습니다.\n{ex.Message}", "오류",
                    MessageBoxButton.OK, MessageBoxImage.Error);
            }
        }
        finally
        {
            reloadPromptOpen = false;
        }
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
            Content = new ActorEditorView
            {
                DataContext = new DatabaseListViewModel<Actor>("액터", context.Actors),
                ProjectRootPath = context.ProjectRootPath,
                Classes = context.Classes,
                Items = context.Items,
            },
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
                States = context.States,
                Animations = context.Animations,
            },
        });
        Tabs.Add(new EditorTab
        {
            Header = "아이템",
            Content = new ItemEditorView
            {
                DataContext = new DatabaseListViewModel<Item>("아이템", context.Items),
                ProjectRootPath = context.ProjectRootPath,
                Types = context.Types,
                Animations = context.Animations,
                States = context.States,
            },
        });
        Tabs.Add(new EditorTab
        {
            Header = "적 캐릭터",
            Content = new EnemyEditorView
            {
                DataContext = new DatabaseListViewModel<Enemy>("적 캐릭터", context.Enemies),
                ProjectRootPath = context.ProjectRootPath,
                Animations = context.Animations,
            },
        });
        Tabs.Add(new EditorTab
        {
            Header = "적 군단",
            Content = new TroopEditorView
            {
                DataContext = new DatabaseListViewModel<Troop>("적 군단", context.Troops),
                ProjectRootPath = context.ProjectRootPath,
                Enemies = context.Enemies,
            },
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
                DataContext = new AnimationListViewModel(context.Animations),
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
