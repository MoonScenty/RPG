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
    /// StaticSv/AnimatedSv면 img/sv_enemies 파일명, DragonBones면 img/dragonbones 아마추어 이름.
    /// DragonBones일 때는 이 이름이 스킬의 Motion 값과 매칭되는 애니메이션 이름으로도 사용된다.
    /// </summary>
    [ObservableProperty]
    private string image = string.Empty;
}
