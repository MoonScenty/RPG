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
}
