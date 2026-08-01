<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skills.json의 네이티브 stypeId(System.json skillTypes: 1=마법/2=필살기) - States.json의
 * STYPE_SEAL 트레잇(code=42, 예: "침묵"이 1/2를 봉인)이 이 스킬을 막아도 되는지
 * 판정하려면 스킬이 자기 타입을 알아야 해서 추가.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mz_skills', function (Blueprint $table) {
            $table->unsignedTinyInteger('stype_id')->default(0)->after('hit_type');
        });
    }

    public function down(): void
    {
        Schema::table('mz_skills', function (Blueprint $table) {
            $table->dropColumn('stype_id');
        });
    }
};
