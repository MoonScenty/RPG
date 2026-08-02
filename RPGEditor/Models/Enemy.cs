using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

public partial class Enemy : DatabaseEntry
{
    public EnemyStats Stats { get; set; } = new();

    [ObservableProperty]
    private int rewardExp;

    [ObservableProperty]
    private int rewardGold;

    public ObservableCollection<EnemyDrop> Drops { get; set; } = [];

    public ObservableCollection<EnemyAction> Actions { get; set; } = [];

    public List<object> Traits { get; set; } = [];

    [ObservableProperty]
    private EnemyImageType imageType = EnemyImageType.StaticSv;

    /// <summary>
    /// StaticSv/AnimatedSv면 img/sv_enemies 파일명, DragonBones면 img/dragonbones 아마추어(스켈레톤) 이름.
    /// </summary>
    [ObservableProperty]
    private string image = string.Empty;

    /// <summary>img/faces 파일명 - 액터와 동일한 얼굴시트 폴더를 공유한다.</summary>
    [ObservableProperty]
    private string faceName = string.Empty;

    /// <summary>DragonBones 아마추어 표시 배율(%). 아마추어마다 원본 크기가 달라 개별 조정이 필요하다.</summary>
    [ObservableProperty]
    private double scale = 100;

    /// <summary>DragonBones일 때 SV 모션 이름과 실제 아마추어 애니메이션 이름의 매칭 테이블.</summary>
    public ObservableCollection<EnemyMotionMapping> MotionMap { get; set; } = CreateDefaultMotionMap();

    private static ObservableCollection<EnemyMotionMapping> CreateDefaultMotionMap() =>
    [
        new() { Motion = "walk", AnimationName = "walk" },
        new() { Motion = "wait", AnimationName = "wait" },
        new() { Motion = "chant", AnimationName = "chant" },
        new() { Motion = "guard", AnimationName = "guard" },
        new() { Motion = "damage", AnimationName = "damage" },
        new() { Motion = "evade", AnimationName = "evade" },
        new() { Motion = "thrust", AnimationName = "thrust" },
        new() { Motion = "swing", AnimationName = "swing" },
        new() { Motion = "missile", AnimationName = "missile" },
        new() { Motion = "skill", AnimationName = "skill" },
        new() { Motion = "spell", AnimationName = "spell" },
        new() { Motion = "item", AnimationName = "item" },
        new() { Motion = "escape", AnimationName = "escape" },
        new() { Motion = "victory", AnimationName = "victory" },
        new() { Motion = "dying", AnimationName = "dying" },
        new() { Motion = "abnormal", AnimationName = "abnormal" },
        new() { Motion = "sleep", AnimationName = "sleep" },
        new() { Motion = "dead", AnimationName = "dead" },
    ];
}
