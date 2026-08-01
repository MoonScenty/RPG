<?php

use Illuminate\Support\Str;

return [

    'driver' => env('SESSION_DRIVER', 'database'),

    // 브라우저 종료 시 세션이 삭제되는 건 바로 아래 expire_on_close가
    // 담당한다 - lifetime을 0으로 두면 안 된다. Sanctum의 CSRF 쿠키
    // (VerifyCsrfToken::newCookie())가 만료시간을 lifetime*60초로 계산하는데,
    // 0이면 "지금"이 돼서 XSRF-TOKEN 쿠키가 발급되자마자 즉시 만료되고
    // 브라우저가 저장을 거부해 로그인이 419로 막히는 실제 장애가 있었다.
    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    'expire_on_close' => true,

    'encrypt' => env('SESSION_ENCRYPT', false),

    'files' => storage_path('framework/sessions'),

    'connection' => env('SESSION_CONNECTION'),

    'table' => env('SESSION_TABLE', 'sessions'),

    'store' => env('SESSION_STORE'),

    'lottery' => [2, 100],

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug((string) env('APP_NAME', 'laravel'), '_').'_session'
    ),

    'path' => env('SESSION_PATH', '/'),

    'domain' => env('SESSION_DOMAIN'),

    // FrontEnd/(SPA)는 BackEnd/와 다른 origin에서 서빙되므로, 크로스 오리진
    // 요청에도 세션 쿠키가 실리려면 SameSite=None + Secure가 필요하다
    // (배포 서버는 항상 HTTPS라 안전하게 켤 수 있다).
    'secure' => env('SESSION_SECURE_COOKIE', true),

    'http_only' => env('SESSION_HTTP_ONLY', true),

    'same_site' => env('SESSION_SAME_SITE', 'none'),

    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

];
