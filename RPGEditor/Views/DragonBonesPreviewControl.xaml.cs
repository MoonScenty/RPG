using System.IO;
using System.Linq;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using Microsoft.Web.WebView2.Core;

namespace RPGEditor.Views;

public partial class DragonBonesPreviewControl : UserControl
{
    private bool _initialized;
    private readonly TaskCompletionSource<bool> _pageReady = new();

    /// <summary>*_ske.json/*_tex.json/*_tex.png 파일이 있는 img/dragonbones 폴더 경로.</summary>
    public string? DragonBonesFolderPath { get; set; }

    public event Action<IReadOnlyList<string>>? AnimationsLoaded;

    public DragonBonesPreviewControl()
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

        var previewFolder = Path.Combine(AppContext.BaseDirectory, "Assets", "DragonBonesPreview");
        WebView.CoreWebView2.SetVirtualHostNameToFolderMapping(
            "dbpreview.local", previewFolder, CoreWebView2HostResourceAccessKind.Allow);

        if (!string.IsNullOrEmpty(DragonBonesFolderPath) && Directory.Exists(DragonBonesFolderPath))
            WebView.CoreWebView2.SetVirtualHostNameToFolderMapping(
                "dbassets.local", DragonBonesFolderPath, CoreWebView2HostResourceAccessKind.Allow);

        WebView.CoreWebView2.WebMessageReceived += OnWebMessageReceived;
        WebView.CoreWebView2.NavigationCompleted += (s, e2) => _pageReady.TrySetResult(e2.IsSuccess);
        WebView.Source = new Uri("https://dbpreview.local/preview.html");
    }

    private void OnWebMessageReceived(object? sender, CoreWebView2WebMessageReceivedEventArgs e)
    {
        using var doc = JsonDocument.Parse(e.WebMessageAsJson);
        var root = doc.RootElement;
        if (root.GetProperty("type").GetString() != "animations")
            return;

        var names = root.GetProperty("names").EnumerateArray().Select(n => n.GetString() ?? string.Empty).ToList();
        AnimationsLoaded?.Invoke(names);
    }

    public async Task LoadArmatureAsync(string armatureName)
    {
        await WebView.EnsureCoreWebView2Async(await WebView2EnvironmentProvider.GetAsync());
        await _pageReady.Task;

        var payload = new
        {
            type = "load",
            armatureName,
            skeUrl = $"https://dbassets.local/{Uri.EscapeDataString(armatureName)}_ske.json",
            texJsonUrl = $"https://dbassets.local/{Uri.EscapeDataString(armatureName)}_tex.json",
            texPngUrl = $"https://dbassets.local/{Uri.EscapeDataString(armatureName)}_tex.png",
        };
        WebView.CoreWebView2.PostWebMessageAsJson(JsonSerializer.Serialize(payload));
    }

    public async Task PlayAnimationAsync(string animationName)
    {
        await WebView.EnsureCoreWebView2Async(await WebView2EnvironmentProvider.GetAsync());
        await _pageReady.Task;
        WebView.CoreWebView2.PostWebMessageAsJson(JsonSerializer.Serialize(new { type = "play", name = animationName }));
    }

    public async Task StopAsync()
    {
        await WebView.EnsureCoreWebView2Async(await WebView2EnvironmentProvider.GetAsync());
        await _pageReady.Task;
        WebView.CoreWebView2.PostWebMessageAsJson(JsonSerializer.Serialize(new { type = "stop" }));
    }
}
