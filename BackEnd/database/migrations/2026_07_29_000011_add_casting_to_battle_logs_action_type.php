<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** 캐스팅 대기 턴을 'cancel'과 구분해서 보여줄 수 있게 action_type enum에 'casting' 추가. */
    public function up(): void
    {
        DB::statement("ALTER TABLE battle_logs MODIFY action_type ENUM('attack', 'skill', 'cancel', 'casting') NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE battle_logs MODIFY action_type ENUM('attack', 'skill', 'cancel') NOT NULL");
    }
};
