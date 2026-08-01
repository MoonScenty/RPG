<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * 소모 아이템 사용을 새 action_type 'item'으로 기록한다. item_name은 스킬 이름을
 * 로그에 안 남기는 기존 관례(skill_id/이름 저장 없음)와 다르게 일부러 저장 - 회복
 * 스킬과 달리 아이템은 "어떤 아이템을 썼는지"가 문구에 직접 필요해서(예: "해독의
 * 물약을 사용해 독을 해제했다") skill_motion처럼 로그 시점에 값을 박아둔다.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE battle_logs MODIFY action_type ENUM('attack', 'skill', 'item', 'cancel', 'casting') NOT NULL");

        Schema::table('battle_logs', function (Blueprint $table) {
            $table->string('item_name', 50)->nullable()->after('skill_motion');
        });
    }

    public function down(): void
    {
        Schema::table('battle_logs', function (Blueprint $table) {
            $table->dropColumn('item_name');
        });

        DB::statement("ALTER TABLE battle_logs MODIFY action_type ENUM('attack', 'skill', 'cancel', 'casting') NOT NULL");
    }
};
