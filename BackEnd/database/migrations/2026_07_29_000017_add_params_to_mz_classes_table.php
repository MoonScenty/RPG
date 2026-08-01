<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Classes.json의 params(8종 스탯 x 레벨1-99 곡선, [paramId][level] 2차원 배열)를
     * 원본 그대로 보관 - ActorSeeder가 여기서 액터의 initialLevel 시점 스탯을 뽑아
     * units에 스냅샷으로 저장한다(MzClass::statAtLevel() 참고).
     */
    public function up(): void
    {
        Schema::table('mz_classes', function (Blueprint $table) {
            $table->json('params')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('mz_classes', function (Blueprint $table) {
            $table->dropColumn('params');
        });
    }
};
