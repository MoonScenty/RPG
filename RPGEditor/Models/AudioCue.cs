using CommunityToolkit.Mvvm.ComponentModel;

namespace RPGEditor.Models;

/// <summary>오디오 파일 참조 + 재생 파라미터. BGM/ME/SE 전반에서 공용으로 사용.</summary>
public partial class AudioCue : ObservableObject
{
    [ObservableProperty]
    private string name = string.Empty;

    [ObservableProperty]
    private int pan;

    [ObservableProperty]
    private int pitch = 100;

    [ObservableProperty]
    private int volume = 90;
}
