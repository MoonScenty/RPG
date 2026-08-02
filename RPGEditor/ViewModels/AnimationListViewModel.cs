using System.Collections.ObjectModel;
using CommunityToolkit.Mvvm.ComponentModel;
using RPGEditor.Models;

namespace RPGEditor.ViewModels;

/// <summary>
/// mz_animations(Animations.json)는 RPG Maker MV 에디터에서만 편집되는 참조
/// 데이터라 Add/Duplicate/Delete/Move가 필요 없다 - DatabaseListViewModel&lt;T&gt;를
/// 재사용하는 대신 목록 탐색(Entries/Selected)만 있는 최소 뷰모델을 둔다.
/// </summary>
public partial class AnimationListViewModel : ObservableObject
{
    public ObservableCollection<MvAnimationData> Entries { get; }

    [ObservableProperty]
    private MvAnimationData? selected;

    public AnimationListViewModel(ObservableCollection<MvAnimationData> entries)
    {
        Entries = entries;
    }
}
