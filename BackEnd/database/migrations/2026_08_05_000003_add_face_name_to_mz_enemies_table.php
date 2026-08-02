<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enemies.json에 신설된 faceName(img/faces 파일명, 액터와 같은 폴더 공유) -
     * mz_actors.face_name과 동일한 개념. 이미 실행된 마이그레이션 파일을 다시
     * 고치면 migrate:fresh 없이는 반영이 안 돼서(계정 테이블까지 날아감 -
     * 2026_08_05_000002 참고) 항상 새 마이그레이션으로 추가한다.
     */
    public function up(): void
    {
        Schema::table('mz_enemies', function (Blueprint $table) {
            $table->string('face_name', 50)->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('mz_enemies', function (Blueprint $table) {
            $table->dropColumn('face_name');
        });
    }
};
