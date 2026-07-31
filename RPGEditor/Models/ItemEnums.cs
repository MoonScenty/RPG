namespace RPGEditor.Models;

public enum ItemKind
{
    Weapon,
    Armor,
    Consumable,
    Material,
}

public enum ItemScope
{
    Self,
    OneEnemy,
    OneAlly,
    AllEnemies,
    AllAllies,
}
