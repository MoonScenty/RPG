<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enemies.json의 note - 지금까지는 안 읽었지만, 적도 이제 이 프로젝트 전용
     * 노트태그(<AttackAnimation: 이름>)를 쓸 수 있게 되면서 필요해졌다(MzNoteTagParser
     * 참고). 원본은 note가 있는데 우리 DB엔 없어서 새로 추가.
     */
    public function up(): void
    {
        Schema::table('mz_enemies', function (Blueprint $table) {
            $table->text('note')->nullable()->after('battler_name');
        });
    }

    public function down(): void
    {
        Schema::table('mz_enemies', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
