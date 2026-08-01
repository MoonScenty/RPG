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
            // preview.local(페이지)과 effects.local/audio.local/img.local(SetVirtualHostNameToFolderMapping로
            // 매핑한 에셋)은 서로 다른 오리진이라, 기본 웹 보안 모델 하에서는 WebGL이 그
            // 이미지를 "오염된(tainted)" 텍스처로 취급해 실제 픽셀 대신 검은색을 반환한다
            // (Effekseer 파티클이 전부 불투명한 검은 사각형으로 나오던 원인). 오직 우리가
            // 직접 서빙하는 로컬 파일만 로드하는 내부 개발 도구라 안전하게 꺼도 된다.
            AdditionalBrowserArguments = "--autoplay-policy=no-user-gesture-required --disable-web-security",
        });
        return _environment;
    }
}
