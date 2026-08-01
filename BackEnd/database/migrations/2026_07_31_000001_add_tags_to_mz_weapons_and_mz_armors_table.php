<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * mz_items.tags와 동일한 패턴 - 무기/방어구 note의 <Crafting> 태그를 시딩 시점에
 * 미리 해석해 저장한다(WeaponSeeder/ArmorSeeder::resolveTags()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mz_weapons', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('traits');
        });
        Schema::table('mz_armors', function (Blueprint $table) {
            $table->json('tags')->nullable()->after('traits');
        });
    }

    public function down(): void
    {
        Schema::table('mz_weapons', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
        Schema::table('mz_armors', function (Blueprint $table) {
            $table->dropColumn('tags');
        });
    }
};
