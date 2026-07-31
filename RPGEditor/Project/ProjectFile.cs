namespace RPGEditor.Project;

public class ProjectFile
{
    public string ProjectName { get; set; } = string.Empty;

    public string Actors { get; set; } = "data/Actors.json";
    public string Classes { get; set; } = "data/Classes.json";
    public string Skills { get; set; } = "data/Skills.json";
    public string Items { get; set; } = "data/Items.json";
    public string Enemies { get; set; } = "data/Enemies.json";
    public string Troops { get; set; } = "data/Troops.json";
    public string States { get; set; } = "data/States.json";
    public string Animations { get; set; } = "data/Animations.json";
    public string Types { get; set; } = "data/Types.json";
}
