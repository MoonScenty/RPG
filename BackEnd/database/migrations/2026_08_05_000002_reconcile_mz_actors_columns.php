<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * character_name/character_index/face_index/nickname 제거, hud_face_name
     * 추가를 이미 적용된 옛 마이그레이션 파일(2026_07_29_000020,
     * 2026_08_03_000001) 안에서 직접 고쳤었다 - 그래서 매번 migrate:fresh로
     * 전체를 다시 만들어야 반영됐고, 그때마다 users 등 계정 테이블까지
     * 통째로 날아가 계정을 계속 새로 만들어야 하는 문제가 있었다(사용자 보고).
     * 이 마이그레이션은 그 두 파일의 순변경분만 별도 파일로 다시 적용해서,
     * 이제부턴 migrate:fresh 없이 평범한 `php artisan migrate`만으로
     * mz_actors를 최신 스키마로 맞출 수 있게 한다. hasColumn으로 감싸서
     * 이미 최신 상태(=마지막으로 fresh를 돌린 뒤 이 파일이 처음 실행되는
     * 경우)에도 안전하게 아무 일도 안 하고 넘어간다.
     */
    public function up(): void
    {
        Schema::table('mz_actors', function (Blueprint $table) {
            if (Schema::hasColumn('mz_actors', 'character_name')) {
                $table->dropColumn('character_name');
            }
            if (Schema::hasColumn('mz_actors', 'character_index')) {
                $table->dropColumn('character_index');
            }
            if (Schema::hasColumn('mz_actors', 'face_index')) {
                $table->dropColumn('face_index');
            }
            if (Schema::hasColumn('mz_actors', 'nickname')) {
                $table->dropColumn('nickname');
            }
            if (! Schema::hasColumn('mz_actors', 'hud_face_name')) {
                $table->string('hud_face_name', 50)->nullable()->after('face_name');
            }
        });
    }

    public function down(): void
    {
        // 이미 적용된 옛 마이그레이션들의 up()이 지금 이 컬럼들을 갖고 있지 않은
        // 최종 스키마를 정의하므로, 여기서 되돌릴 "이전 상태"가 애매하다 - 이
        // 마이그레이션 자체를 롤백할 일은 없다고 보고 down()은 비워둔다.
    }
};
