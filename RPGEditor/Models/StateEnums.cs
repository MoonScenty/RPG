namespace RPGEditor.Models;

public enum StateRestriction
{
    None,
    AttackEnemy,
    AttackAlly,
    AttackAnyone,
    CannotMove,
}

public enum StateAutoRemovalTiming
{
    None,
    TurnEnd,
    ActionEnd,
}
