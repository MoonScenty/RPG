<?php

use Illuminate\Support\Facades\Route;

// FrontEnd/(Vue SPA)는 vite build로 public/ 안에 바로 들어온다(index.html +
// assets/) - api/sanctum이 아닌 모든 경로는 그 index.html을 그대로 돌려줘서
// vue-router의 클라이언트 사이드 라우팅(history 모드)이 새로고침에도
// 살아있게 한다. 아직 빌드 전(로컬에 index.html이 없음)이면 API가 떠
// 있는지 확인용 JSON으로 대체한다.
Route::get('/{any?}', function () {
    $indexPath = public_path('index.html');

    if (! file_exists($indexPath)) {
        return response()->json(['name' => 'RPG API']);
    }

    return response()->file($indexPath);
})->where('any', '^(?!api|sanctum).*$');
