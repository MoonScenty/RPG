<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** spawn 시점 Unit 스냅샷에 class_id/mat/mdf도 같이 복사(atk/def/spd와 동일 패턴). */
    public function up(): void
    {
        Schema::table('battle_units', function (Blueprint $table) {
            // foreignId()는 항상 unsignedBigInteger라 mz_classes.id(unsignedTinyInteger)와
            // 타입이 안 맞아 FK 생성이 실패한다 - 타입을 맞춰서 직접 선언.
            $table->unsignedTinyInteger('class_id')->nullable()->after('unit_id');
            $table->unsignedInteger('mat')->default(0)->after('def');
            $table->unsignedInteger('mdf')->default(0)->after('mat');
            $table->foreign('class_id')->references('id')->on('mz_classes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('battle_units', function (Blueprint $table) {
            $table->dropForeign(['class_id']);
            $table->dropColumn(['class_id', 'mat', 'mdf']);
        });
    }
};
