using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

/// <summary>
/// RPG Maker MV 원본 스프라이트시트 셀 애니메이션 포맷(data/Animations_mv.json) 그대로.
/// Frames/Timings는 실제 RPG Maker MV 에디터에서만 편집되는 참조 전용 데이터라
/// RPGEditor에서는 읽기 전용으로만 다룬다(미리보기 재생 + Weapon.AnimationId/
/// SkillInvocation.AnimationId 선택 용도) - 프레임 단위 셀 배치 편집기는 만들지 않는다.
/// </summary>
public partial class MvAnimationData : ObservableObject
{
    [ObservableProperty]
    private int id;

    [ObservableProperty]
    private string name = string.Empty;

    /// <summary>img/animations/{값}.png - 5열 그리드, 셀 192x192, pattern 0~99가 이 시트를 가리킴.</summary>
    [ObservableProperty]
    private string? animation1Name;

    [ObservableProperty]
    private int animation1Hue;

    /// <summary>pattern 100 이상이 가리키는 두 번째 시트.</summary>
    [ObservableProperty]
    private string? animation2Name;

    [ObservableProperty]
    private int animation2Hue;

    /// <summary>0=머리, 1=중앙(기본), 2=발밑, 3=화면 고정.</summary>
    [ObservableProperty]
    private int position = 1;

    /// <summary>프레임마다 셀 목록 - 셀 하나는 [pattern, x, y, scale, rotation, mirror, opacity, blendType] 8개 값.</summary>
    public List<List<int[]>> Frames { get; set; } = [];

    public List<MvAnimationTiming> Timings { get; set; } = [];
}

/// <summary>frame당 SE/플래시 트리거 - MvAnimationData.Timings 항목.</summary>
public partial class MvAnimationTiming : ObservableObject
{
    [ObservableProperty]
    private int frame;

    /// <summary>SE 없는 타이밍(플래시만)도 있어서 null 허용.</summary>
    public AudioCue? Se { get; set; }

    /// <summary>[R, G, B, A], 각 0~255.</summary>
    public int[] FlashColor { get; set; } = [255, 255, 255, 255];

    [ObservableProperty]
    private int flashDuration;

    /// <summary>0=없음, 1=대상 플래시, 2=화면 플래시, 3=대상 숨김.</summary>
    [ObservableProperty]
    private int flashScope;
}
