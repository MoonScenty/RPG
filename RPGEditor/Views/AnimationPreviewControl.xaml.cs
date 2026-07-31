using System.IO;
using System.Linq;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using Microsoft.Web.WebView2.Core;
using RPGEditor.Models;

namespace RPGEditor.Views;

public partial class AnimationPreviewControl : UserControl
{
    private bool _initialized;
    private readonly TaskCompletionSource<bool> _pageReady = new();

    /// <summary>이펙트(effects)/오디오(audio) 폴더를 찾기 위한 프로젝트 루트 경로.</summary>
    public string? ProjectRootPath { get; set; }

    public AnimationPreviewControl()
    {
        InitializeComponent();
        Loaded += OnLoaded;
    }

    private async void OnLoaded(object sender, RoutedEventArgs e)
    {
        if (_initialized)
            return;
        _initialized = true;

        await WebView.EnsureCoreWebView2Async(await WebView2EnvironmentProvider.GetAsync());

        var previewFolder = Path.Combine(AppContext.BaseDirectory, "Assets", "AnimationPreview");
        WebView.CoreWebView2.SetVirtualHostNameToFolderMapping(
            "preview.local", previewFolder, CoreWebView2HostResourceAccessKind.Allow);

        if (!string.IsNullOrEmpty(ProjectRootPath))
        {
            var effectsFolder = Path.Combine(ProjectRootPath, "effects");
            var audioFolder = Path.Combine(ProjectRootPath, "audio");

            if (Directory.Exists(effectsFolder))
                WebView.CoreWebView2.SetVirtualHostNameToFolderMapping(
                    "effects.local", effectsFolder, CoreWebView2HostResourceAccessKind.Allow);

            if (Directory.Exists(audioFolder))
                WebView.CoreWebView2.SetVirtualHostNameToFolderMapping(
                    "audio.local", audioFolder, CoreWebView2HostResourceAccessKind.Allow);
        }

        WebView.CoreWebView2.NavigationCompleted += (s, e) => _pageReady.TrySetResult(e.IsSuccess);
        WebView.Source = new Uri("https://preview.local/preview.html");
    }

    public async Task PlayAsync(AnimationData? animation)
    {
        if (animation is null)
            return;

        await WebView.EnsureCoreWebView2Async(await WebView2EnvironmentProvider.GetAsync());
        await _pageReady.Task;

        var payload = new
        {
            type = "play",
            name = animation.Name,
            effectUrl = $"https://effects.local/{Uri.EscapeDataString(animation.EffectName)}.efkefc",
            offsetX = animation.OffsetX,
            offsetY = animation.OffsetY,
            scale = animation.Scale,
            speed = animation.Speed,
            rotation = new { x = animation.Rotation.X, y = animation.Rotation.Y, z = animation.Rotation.Z },
            flashTimings = animation.FlashTimings.Select(f => new { frame = f.Frame, duration = f.Duration, color = f.Color }),
            soundTimings = animation.SoundTimings.Select(s => new
            {
                frame = s.Frame,
                se = new { name = s.Se.Name, pan = s.Se.Pan, pitch = s.Se.Pitch, volume = s.Se.Volume },
            }),
        };

        WebView.CoreWebView2.PostWebMessageAsJson(JsonSerializer.Serialize(payload));
    }

    public async Task StopAsync()
    {
        await WebView.EnsureCoreWebView2Async(await WebView2EnvironmentProvider.GetAsync());
        await _pageReady.Task;
        WebView.CoreWebView2.PostWebMessageAsJson(JsonSerializer.Serialize(new { type = "stop" }));
    }
}
