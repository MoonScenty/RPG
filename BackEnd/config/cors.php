<?php

return [

    // frontend/(SPA)가 backend/와 다른 origin에서 서빙되므로 CORS를 열어야 한다.
    // 이전 CI4 백엔드에서 mz_project 로컬 테스트용 origin을 여기 등록해뒀던
    // 것과 같은 이유 - 실제 배포/개발 origin에 맞게 CORS_ALLOWED_ORIGINS를
    // 콤마로 구분해 설정한다.
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // 세션 쿠키 기반 로그인이라 크로스 오리진 요청에 인증 정보가 실리려면
    // 반드시 true여야 한다.
    'supports_credentials' => true,

];
