<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * mz_skills.tags와 동일한 패턴 - ItemSeeder가 Items.json의 effects[](code11=HP회복,
 * 12=MP회복, 21=상태 부여, 22=상태 해제)와 note 커스텀 태그(ApplySelfState/ExcludeSelf)를
 * 파싱해서 여기 담는다. BattleEngine::resolveItemUse()가 이걸 읽어서 실제로 적용한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mz_items', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('effects');
        });
    }

    public function down(): void
    {
        Schema::table('mz_items', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }
};
