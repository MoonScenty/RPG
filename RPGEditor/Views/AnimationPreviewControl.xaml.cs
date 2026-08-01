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

        // effects/audio/img를 예전엔 effects.local/audio.local/img.local이라는 별도
        // 가상 호스트로 매핑했는데, 그러면 preview.local(페이지)과 서로 다른
        // 오리진이 되어 WebGL이 텍스처를 오염된(tainted) 것으로 취급해 검게
        // 렌더링하는 문제가 있었다(--disable-web-security로 우회를 시도했지만
        // WebView2가 이 플래그를 지원하지 않아 효과가 없었음). 대신 같은
        // preview.local 오리진의 /effects//audio//img/ 하위 경로 요청을 직접
        // 가로채서 프로젝트 폴더 파일로 응답한다 - 전부 진짜 같은 오리진이 된다.
        WebView.CoreWebView2.AddWebResourceRequestedFilter(
            "https://preview.local/*", CoreWebView2WebResourceContext.All);
        WebView.CoreWebView2.WebResourceRequested += OnWebResourceRequested;

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
            effectUrl = $"/effects/{Uri.EscapeDataString(animation.EffectName)}.efkefc",
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

    /// <summary>
    /// https://preview.local/effects/**, /audio/**, /img/**를 프로젝트 폴더(ProjectRootPath)
    /// 파일로 직접 서빙한다 - preview.html/js는 이 경로들을 상대 경로로 요청한다.
    /// </summary>
    private void OnWebResourceRequested(object? sender, CoreWebView2WebResourceRequestedEventArgs e)
    {
        if (string.IsNullOrEmpty(ProjectRootPath))
            return;

        var trimmedPath = new Uri(e.Request.Uri).AbsolutePath.TrimStart('/'); // "effects/Poison.efkefc" 등
        var slashIndex = trimmedPath.IndexOf('/');
        if (slashIndex < 0)
            return;

        var subfolder = trimmedPath[..slashIndex];
        if (subfolder is not ("effects" or "audio" or "img"))
            return;

        var relativePath = Uri.UnescapeDataString(trimmedPath[(slashIndex + 1)..]);
        var filePath = Path.Combine(ProjectRootPath, subfolder, relativePath.Replace('/', Path.DirectorySeparatorChar));

        var environment = WebView.CoreWebView2.Environment;
        if (!File.Exists(filePath))
        {
            e.Response = environment.CreateWebResourceResponse(null, 404, "Not Found", "");
            return;
        }

        var stream = File.OpenRead(filePath);
        var contentType = ContentTypeFor(filePath);
        e.Response = environment.CreateWebResourceResponse(
            stream, 200, "OK", $"Content-Type: {contentType}\nAccess-Control-Allow-Origin: *");
    }

    private static string ContentTypeFor(string filePath) => Path.GetExtension(filePath).ToLowerInvariant() switch
    {
        ".png" => "image/png",
        ".json" => "application/json",
        ".js" => "text/javascript",
        ".wasm" => "application/wasm",
        ".ogg" => "audio/ogg",
        ".efkefc" or ".efk" => "application/octet-stream",
        _ => "application/octet-stream",
    };
}
