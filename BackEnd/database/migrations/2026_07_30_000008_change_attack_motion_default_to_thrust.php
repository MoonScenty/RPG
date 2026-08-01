<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 기본 공격 모션 기본값을 swing -> thrust로 변경(System.json attackMotions를
 * 전부 type 0=thrust로 맞춘 것과 동일한 방향). MzImportSeeder가 재시딩 시
 * units.attack_motion을 명시적으로 다시 써서 채우지만(ActorSeeder/
 * syncEnemyUnits), 컬럼 기본값 자체도 같이 맞춰 새 INSERT 경로에서 어긋나지
 * 않게 한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE units MODIFY attack_motion ENUM('thrust', 'swing', 'missile') NOT NULL DEFAULT 'thrust'");
        DB::statement("ALTER TABLE battle_units MODIFY attack_motion ENUM('thrust', 'swing', 'missile') NOT NULL DEFAULT 'thrust'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE units MODIFY attack_motion ENUM('thrust', 'swing', 'missile') NOT NULL DEFAULT 'swing'");
        DB::statement("ALTER TABLE battle_units MODIFY attack_motion ENUM('thrust', 'swing', 'missile') NOT NULL DEFAULT 'swing'");
    }
};
