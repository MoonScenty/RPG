using System.Collections.ObjectModel;
using System.IO;
using System.Text.Json;
using RPGEditor.Models;

namespace RPGEditor.Project;

public class ProjectContext
{
    private static readonly string[] AudioFolders = ["bgm", "bgs", "me", "se"];
    private static readonly string[] EffectFolders = ["Model", "Texture"];
    private static readonly string[] ImageFolders =
    [
        "battleback1", "battleback2", "magic_circles", "dragonbones",
        "faces", "pictures", "sv_actors", "sv_enemies", "system",
    ];

    private static readonly JsonSerializerOptions JsonOptions = new()
    {
        WriteIndented = true,
    };

    public string ProjectFilePath { get; }
    public string ProjectRootPath { get; }
    public ProjectFile ProjectFile { get; }

    public ObservableCollection<Actor> Actors { get; } = [];
    public ObservableCollection<CharacterClass> Classes { get; } = [];
    public ObservableCollection<Skill> Skills { get; } = [];
    public ObservableCollection<Item> Items { get; } = [];
    public ObservableCollection<Enemy> Enemies { get; } = [];
    public ObservableCollection<Troop> Troops { get; } = [];
    public ObservableCollection<GameState> States { get; } = [];
    public ObservableCollection<AnimationData> Animations { get; } = [];
    public ObservableCollection<EntryType> Types { get; } = [];
    public SystemData System { get; set; } = new();

    private ProjectContext(string projectFilePath, ProjectFile projectFile)
    {
        ProjectFilePath = projectFilePath;
        ProjectRootPath = Path.GetDirectoryName(projectFilePath)!;
        ProjectFile = projectFile;
    }

    public static ProjectContext CreateNew(string parentFolder, string projectName)
    {
        var root = Path.Combine(parentFolder, projectName);
        Directory.CreateDirectory(root);

        foreach (var folder in AudioFolders)
            Directory.CreateDirectory(Path.Combine(root, "audio", folder));
        foreach (var folder in EffectFolders)
            Directory.CreateDirectory(Path.Combine(root, "effects", folder));
        foreach (var folder in ImageFolders)
            Directory.CreateDirectory(Path.Combine(root, "img", folder));
        Directory.CreateDirectory(Path.Combine(root, "data"));

        var projectFile = new ProjectFile { ProjectName = projectName };
        var projectFilePath = Path.Combine(root, $"{projectName}.rpgprj");

        var context = new ProjectContext(projectFilePath, projectFile);
        context.Save();
        return context;
    }

    public static ProjectContext Load(string projectFilePath)
    {
        var json = File.ReadAllText(projectFilePath);
        var projectFile = JsonSerializer.Deserialize<ProjectFile>(json)
            ?? throw new InvalidDataException($"프로젝트 파일을 읽을 수 없습니다: {projectFilePath}");

        var context = new ProjectContext(projectFilePath, projectFile);

        LoadEntries(context.ProjectRootPath, projectFile.Actors, context.Actors);
        LoadEntries(context.ProjectRootPath, projectFile.Classes, context.Classes);
        LoadEntries(context.ProjectRootPath, projectFile.Skills, context.Skills);
        LoadEntries(context.ProjectRootPath, projectFile.Items, context.Items);
        LoadEntries(context.ProjectRootPath, projectFile.Enemies, context.Enemies);
        LoadEntries(context.ProjectRootPath, projectFile.Troops, context.Troops);
        LoadEntries(context.ProjectRootPath, projectFile.States, context.States);
        LoadEntries(context.ProjectRootPath, projectFile.Animations, context.Animations);
        LoadEntries(context.ProjectRootPath, projectFile.Types, context.Types);

        var systemPath = Path.Combine(context.ProjectRootPath, projectFile.System);
        context.System = File.Exists(systemPath)
            ? JsonSerializer.Deserialize<SystemData>(File.ReadAllText(systemPath)) ?? new SystemData()
            : new SystemData();

        return context;
    }

    public void Save()
    {
        Directory.CreateDirectory(Path.Combine(ProjectRootPath, "data"));

        File.WriteAllText(ProjectFilePath, JsonSerializer.Serialize(ProjectFile, JsonOptions));

        SaveEntries(ProjectFile.Actors, Actors);
        SaveEntries(ProjectFile.Classes, Classes);
        SaveEntries(ProjectFile.Skills, Skills);
        SaveEntries(ProjectFile.Items, Items);
        SaveEntries(ProjectFile.Enemies, Enemies);
        SaveEntries(ProjectFile.Troops, Troops);
        SaveEntries(ProjectFile.States, States);
        SaveEntries(ProjectFile.Animations, Animations);
        SaveEntries(ProjectFile.Types, Types);

        var systemPath = Path.Combine(ProjectRootPath, ProjectFile.System);
        File.WriteAllText(systemPath, JsonSerializer.Serialize(System, JsonOptions));
    }

    private static void LoadEntries<T>(string root, string relativePath, ObservableCollection<T> target)
        where T : DatabaseEntry
    {
        var path = Path.Combine(root, relativePath);
        if (!File.Exists(path))
            return;

        var entries = JsonSerializer.Deserialize<List<T>>(File.ReadAllText(path));
        if (entries is null)
            return;

        target.Clear();
        foreach (var entry in entries)
            target.Add(entry);
    }

    private void SaveEntries<T>(string relativePath, ObservableCollection<T> source)
        where T : DatabaseEntry
    {
        var path = Path.Combine(ProjectRootPath, relativePath);
        Directory.CreateDirectory(Path.GetDirectoryName(path)!);
        File.WriteAllText(path, JsonSerializer.Serialize(source, JsonOptions));
    }
}
