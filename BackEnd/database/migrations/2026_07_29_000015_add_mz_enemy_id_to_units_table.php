<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 적 유닛(type=enemy) 카탈로그 행을 mz_enemies와 연결 - MzImportSeeder가 이름이
     * 있는(빈 슬롯 아닌) mz_enemies마다 이 컬럼으로 연결된 units 행을 자동 upsert한다.
     * 아군은 계속 null(직업/스탯은 class_id 쪽으로).
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->unsignedTinyInteger('mz_enemy_id')->nullable()->after('class_id');
            $table->foreign('mz_enemy_id')->references('id')->on('mz_enemies')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropForeign(['mz_enemy_id']);
            $table->dropColumn('mz_enemy_id');
        });
    }
};
