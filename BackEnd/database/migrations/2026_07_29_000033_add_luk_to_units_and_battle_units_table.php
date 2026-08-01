<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 치명타 확률 계산용 LUK(행운) 스탯 - mat/mdf 추가 때와 달리 지금까지는 일부러
 * 빼뒀었지만(Classes.json/Enemies.json params[7]), 치명타 구현을 위해 새로 도입한다.
 * ActorSeeder는 MzClass::statAtLevel()+MzWeapon::statBonuses()가 반환하는 luk 키를
 * 그대로 스냅샷하고(mat/mdf와 동일 패턴), MzImportSeeder::syncEnemyUnits()는
 * mz_enemies.luk를 그대로 복사한다.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->unsignedInteger('luk')->default(0)->after('mdf');
        });
        Schema::table('battle_units', function (Blueprint $table) {
            $table->unsignedInteger('luk')->default(0)->after('mdf');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('luk');
        });
        Schema::table('battle_units', function (Blueprint $table) {
            $table->dropColumn('luk');
        });
    }
};
