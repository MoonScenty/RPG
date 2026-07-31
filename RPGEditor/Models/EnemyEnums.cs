namespace RPGEditor.Models;

public enum EnemyImageType
{
    DragonBones,
    AnimatedSv,
    StaticSv,
}

public enum EnemyActionConditionType
{
    Always,
    TurnNumber,
    SelfHpRange,
    SelfMpRange,
    HasState,
}
