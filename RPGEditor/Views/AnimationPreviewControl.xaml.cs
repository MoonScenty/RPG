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

    /// <summary>스프라이트시트(img/animations)/오디오(audio) 폴더를 찾기 위한 프로젝트 루트 경로.</summary>
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

        // preview.local 전체를 SetVirtualHostNameToFolderMapping이 아니라
        // WebResourceRequested로만 서빙한다. 처음엔 preview.local은 폴더 매핑,
        // effects/audio/img만 WebResourceRequested로 가로채려 했는데, 실제로는
        // SetVirtualHostNameToFolderMapping이 그 호스트의 모든 요청을 자기
        // 매핑 폴더(Assets/AnimationPreview) 기준으로 먼저 처리해버려서
        // WebResourceRequested가 effects/audio/img 경로에 대해 전혀 호출되지
        // 않았다(전부 ERR_FILE_NOT_FOUND로 실패 - 사용자 콘솔에서 확인). 그래서
        // 폴더 매핑은 아예 안 쓰고 이 핸들러 하나가 preview.local의 모든 요청을
        // 처리한다: /audio, /img는 프로젝트 폴더에서, 나머지(/, preview.html,
        // preview.js)는 앱 번들 폴더에서.
        WebView.CoreWebView2.AddWebResourceRequestedFilter(
            "https://preview.local/*", CoreWebView2WebResourceContext.All);
        WebView.CoreWebView2.WebResourceRequested += OnWebResourceRequested;

        WebView.CoreWebView2.NavigationCompleted += (s, e) => _pageReady.TrySetResult(e.IsSuccess);
        WebView.Source = new Uri("https://preview.local/preview.html");
    }

    public async Task PlayAsync(MvAnimationData? animation)
    {
        if (animation is null)
            return;

        await WebView.EnsureCoreWebView2Async(await WebView2EnvironmentProvider.GetAsync());
        await _pageReady.Task;

        var payload = new
        {
            type = "play",
            name = animation.Name,
            animation1Url = animation.Animation1Name is { Length: > 0 } a1
                ? $"/img/animations/{Uri.EscapeDataString(a1)}.png"
                : null,
            animation1Hue = animation.Animation1Hue,
            animation2Url = animation.Animation2Name is { Length: > 0 } a2
                ? $"/img/animations/{Uri.EscapeDataString(a2)}.png"
                : null,
            animation2Hue = animation.Animation2Hue,
            position = animation.Position,
            frames = animation.Frames,
            timings = animation.Timings.Select(t => new
            {
                frame = t.Frame,
                se = t.Se is null ? null : new { name = t.Se.Name, pan = t.Se.Pan, pitch = t.Se.Pitch, volume = t.Se.Volume },
                flashColor = t.FlashColor,
                flashDuration = t.FlashDuration,
                flashScope = t.FlashScope,
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
    /// preview.local의 모든 요청을 이 핸들러 하나가 처리한다: /audio, /img(img/animations
    /// 스프라이트시트 포함) 하위 경로는 프로젝트 폴더(ProjectRootPath)에서, 나머지(빈
    /// 경로 포함 - preview.html/preview.js)는 앱 번들의 Assets/AnimationPreview
    /// 폴더에서 읽어 응답한다.
    /// </summary>
    private void OnWebResourceRequested(object? sender, CoreWebView2WebResourceRequestedEventArgs e)
    {
        var path = Uri.UnescapeDataString(new Uri(e.Request.Uri).AbsolutePath.TrimStart('/'));
        if (path.Length == 0)
            path = "preview.html";

        string filePath;
        var slashIndex = path.IndexOf('/');
        var subfolder = slashIndex > 0 ? path[..slashIndex] : null;
        if (subfolder is "effects" or "audio" or "img" && !string.IsNullOrEmpty(ProjectRootPath))
        {
            var relativePath = path[(slashIndex + 1)..].Replace('/', Path.DirectorySeparatorChar);
            filePath = Path.Combine(ProjectRootPath, subfolder, relativePath);
        }
        else
        {
            var previewFolder = Path.Combine(AppContext.BaseDirectory, "Assets", "AnimationPreview");
            filePath = Path.Combine(previewFolder, path.Replace('/', Path.DirectorySeparatorChar));
        }

        var environment = WebView.CoreWebView2.Environment;
        if (!File.Exists(filePath))
        {
            e.Response = environment.CreateWebResourceResponse(null, 404, "Not Found", "");
            return;
        }

        var stream = File.OpenRead(filePath);
        var contentType = ContentTypeFor(filePath);
        e.Response = environment.CreateWebResourceResponse(stream, 200, "OK", $"Content-Type: {contentType}");
    }

    private static string ContentTypeFor(string filePath) => Path.GetExtension(filePath).ToLowerInvariant() switch
    {
        ".html" => "text/html",
        ".png" => "image/png",
        ".json" => "application/json",
        ".js" => "text/javascript",
        ".ogg" => "audio/ogg",
        _ => "application/octet-stream",
    };
}
