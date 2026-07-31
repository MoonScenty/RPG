using Microsoft.Web.WebView2.Core;

namespace RPGEditor.Views;

/// <summary>
/// 모든 미리보기용 WebView2 컨트롤이 공유하는 환경.
/// 사용자 제스처 없이도(예: C# → JS 메시지로 트리거된 재생) 오디오 자동재생이 차단되지 않도록 설정한다.
/// 같은 프로세스 안의 모든 WebView2 인스턴스는 반드시 동일한 환경으로 초기화되어야 하므로
/// (그렇지 않으면 "already initialized with a different CoreWebView2Environment" 예외 발생),
/// 모든 미리보기 컨트롤의 모든 EnsureCoreWebView2Async 호출은 이 환경을 통해서만 이루어져야 한다.
/// </summary>
internal static class WebView2EnvironmentProvider
{
    private static Task<CoreWebView2Environment>? _environment;

    public static Task<CoreWebView2Environment> GetAsync()
    {
        _environment ??= CoreWebView2Environment.CreateAsync(options: new CoreWebView2EnvironmentOptions
        {
            AdditionalBrowserArguments = "--autoplay-policy=no-user-gesture-required",
        });
        return _environment;
    }
}
