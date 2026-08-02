using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class Skill : DatabaseEntry
{
    [ObservableProperty]
    private int iconIndex;

    [ObservableProperty]
    private string description = string.Empty;

    /// <summary>Types.json이 참조하는 스킬 유형 ID.</summary>
    [ObservableProperty]
    private int skillTypeId;

    [ObservableProperty]
    private int mpCost;

    [ObservableProperty]
    private SkillScope scope = SkillScope.OneEnemy;

    public SkillInvocation Invocation { get; set; } = new();

    /// <summary>%1=자신, %2=스킬명, %3=대상.</summary>
    [ObservableProperty]
    private string message = string.Empty;

    public SkillDamage Damage { get; set; } = new();

    public ObservableCollection<SkillEffect> Effects { get; set; } = [];

    [ObservableProperty]
    private SkillUsablePosition usablePosition = SkillUsablePosition.Any;

    /// <summary>사용 시 재생할 SV 배틀러 모션 이름.</summary>
    [ObservableProperty]
    private string motion = string.Empty;

    /// <summary>스킬 사용에 필요한 States.json ID. null이면 요구 상태 없음(0번 상태가
    /// 실제로 등록돼 있을 수 있어 0을 "없음" sentinel로 못 씀).</summary>
    [ObservableProperty]
    private int? requiredStateId;

    [ObservableProperty]
    private bool removeRequiredStateOnUse;

    /// <summary>img/magic_circles 참조. 빈 문자열이면 마법진 없음.</summary>
    [ObservableProperty]
    private string magicCircleImage = string.Empty;

    /// <summary>마법진 이미지 표시 배율 - 1이 원본 크기(퍼센트 아님, Pixi scale.set()에 그대로 씀).</summary>
    [ObservableProperty]
    private double magicCircleScale = 1;

    /// <summary>캐스팅(시전 대기) 턴 수. null이면 즉시 발동(캐스팅 없음).</summary>
    [ObservableProperty]
    private int? castingTurns;

    /// <summary>재사용 대기시간(턴). null이면 쿨다운 없음.</summary>
    [ObservableProperty]
    private int? cooldownTurns;

    /// <summary>MP 소모 후 시전자에게 추가로 회복시킬 MP 고정치. null이면 회복 없음.</summary>
    [ObservableProperty]
    private int? mpRecoverOnUse;

    /// <summary>가한 피해의 몇 %를 시전자 HP로 흡수할지. null이면 흡혈 없음.</summary>
    [ObservableProperty]
    private int? lifestealPercent;

    /// <summary>시전자 자신의 ATB 게이지를 즉시 올리는 양. null이면 없음.</summary>
    [ObservableProperty]
    private int? gaugeBoostAmount;

    /// <summary>사용 시 MP를 전부 소모(잔여 MP 그대로 소진)하는지.</summary>
    [ObservableProperty]
    private bool consumeAllMp;

    /// <summary>사용 시 시전자를 전열/후열 반대 줄로 옮기는지("자리 변경" 계열 스킬 전용).</summary>
    [ObservableProperty]
    private bool swapPosition;

    /// <summary>전투불능 상태인 아군도 대상으로 고를 수 있는지(부활 스킬 등, 아군 대상 스킬 전용).</summary>
    [ObservableProperty]
    private bool targetDeadAllies;

    /// <summary>대상이 이 상태를 갖고 있어야만 대상으로 고를 수 있음(예: "확인사살"류 - 대상이
    /// 특정 상태일 때만 사용 가능). null이면 제약 없음.</summary>
    [ObservableProperty]
    private int? requireTargetStateId;

    /// <summary>"만약 시전자가 이 상태를 갖고 있다면" 조건 - 시전 시점 상태를 기준으로 판정.</summary>
    [ObservableProperty]
    private int? selfHasStateId;

    /// <summary>SelfHasStateId 조건이 참일 때 시전자에게 부여할 상태.</summary>
    [ObservableProperty]
    private int? selfHasAppliesStateId;

    /// <summary>"만약 대상이 이 상태를 갖고 있다면" 조건(명중했을 때만) - 시전 시점 상태를 기준으로 판정.</summary>
    [ObservableProperty]
    private int? targetHasStateId;

    /// <summary>TargetHasStateId 조건이 참일 때 대상에게 부여할 상태.</summary>
    [ObservableProperty]
    private int? targetHasAppliesStateId;
}
