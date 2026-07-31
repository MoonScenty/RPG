namespace RPGEditor.Models;

public enum SkillScope
{
    OneEnemy,
    AllEnemies,
    OneAlly,
    AllAllies,
    User,
    EnemyFrontRow,
    EnemyBackRow,
    AllyFrontRow,
    AllyBackRow,
}

public enum SkillHitType
{
    Certain,
    Physical,
    Magical,
}

public enum DamageType
{
    None,
    HpDamage,
    MpDamage,
    HpRecover,
    MpRecover,
    HpDrain,
    MpDrain,
}

public enum SkillUsablePosition
{
    Front,
    Back,
    Any,
}

public enum SkillEffectKind
{
    TargetRecoverHp,
    TargetRecoverMp,
    TargetRecoverTp,
    UserRecoverHp,
    UserRecoverMp,
    UserRecoverTp,
    TargetAddState,
    TargetRemoveState,
    UserAddState,
    UserRemoveState,
    AtbBoost,
}
