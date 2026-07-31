using System.IO;
using System.Text.Json;
using System.Windows;
using System.Windows.Controls;
using Microsoft.Web.WebView2.Core;

namespace RPGEditor.Views;

public partial class AudioPreviewControl : UserControl
{
    private bool _initialized;
    private readonly TaskCompletionSource<bool> _pageReady = new();

    /// <summary>재생할 오디오 파일(.ogg)이 있는 폴더 경로. 예: audio/bgm.</summary>
    public string? AudioFolderPath { get; set; }

    public event Action<string>? StatusChanged;

    public AudioPreviewControl()
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

        var previewFolder = Path.Combine(AppContext.BaseDirectory, "Assets", "AudioPreview");
        WebView.CoreWebView2.SetVirtualHostNameToFolderMapping(
            "audiopreview.local", previewFolder, CoreWebView2HostResourceAccessKind.Allow);

        if (!string.IsNullOrEmpty(AudioFolderPath) && Directory.Exists(AudioFolderPath))
            WebView.CoreWebView2.SetVirtualHostNameToFolderMapping(
                "audiofile.local", AudioFolderPath, CoreWebView2HostResourceAccessKind.Allow);

        WebView.CoreWebView2.NavigationCompleted += (s, e2) => _pageReady.TrySetResult(e2.IsSuccess);
        WebView.CoreWebView2.WebMessageReceived += OnWebMessageReceived;
        WebView.Source = new Uri("https://audiopreview.local/preview.html");
    }

    private void OnWebMessageReceived(object? sender, CoreWebView2WebMessageReceivedEventArgs e)
    {
        using var doc = JsonDocument.Parse(e.WebMessageAsJson);
        var root = doc.RootElement;
        if (root.GetProperty("type").GetString() != "status")
            return;

        StatusChanged?.Invoke(root.GetProperty("text").GetString() ?? string.Empty);
    }

    public async Task PlayAsync(string fileName, int pan, int pitch, int volume)
    {
        if (string.IsNullOrEmpty(fileName))
            return;

        await WebView.EnsureCoreWebView2Async(await WebView2EnvironmentProvider.GetAsync());
        await _pageReady.Task;

        var payload = new
        {
            type = "play",
            name = fileName,
            url = $"https://audiofile.local/{Uri.EscapeDataString(fileName)}.ogg",
            pan,
            pitch,
            volume,
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
