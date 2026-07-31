using RPGEditor.Models;

namespace RPGEditor.ViewModels;

public class TypesEditorViewModel
{
    public NamedListViewModel Elements { get; }
    public NamedListViewModel SkillTypes { get; }
    public NamedListViewModel WeaponTypes { get; }
    public NamedListViewModel ArmorTypes { get; }
    public NamedListViewModel EquipTypes { get; }

    public TypesEditorViewModel(TypesData data)
    {
        Elements = new NamedListViewModel("속성", data.Elements);
        SkillTypes = new NamedListViewModel("스킬 유형", data.SkillTypes);
        WeaponTypes = new NamedListViewModel("무기 유형", data.WeaponTypes);
        ArmorTypes = new NamedListViewModel("방어구 유형", data.ArmorTypes);
        EquipTypes = new NamedListViewModel("장비 유형", data.EquipTypes);
    }
}
