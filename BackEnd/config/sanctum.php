<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | frontend/(Vue SPA)가 backend/와 다른 origin에서 서빙되므로, 세션 쿠키 기반 인증을
    | 쓰려면 그 origin이 여기 등록돼 있어야 한다. 로컬 개발 포트와 실제
    | 배포 도메인을 SANCTUM_STATEFUL_DOMAINS 환경변수로 관리한다.
    */

    'stateful' => explode(',', (string) env(
        'SANCTUM_STATEFUL_DOMAINS',
        sprintf(
            '%s%s',
            'localhost,localhost:3000,localhost:5173,127.0.0.1,127.0.0.1:8000,::1',
            Sanctum::currentApplicationUrlWithPort()
        )
    )),

    'guard' => ['web'],

    'expiration' => null,

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
