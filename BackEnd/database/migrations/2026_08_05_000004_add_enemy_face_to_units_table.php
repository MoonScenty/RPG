<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enemies.json의 faceName(img/faces 파일명, 액터처럼 8칸 시트가 아니라 적
     * 한 마리당 독립된 이미지 파일 한 장 - 사용자 지시) - units.sprite는 적의
     * 경우 이미 "Enemy:{image}"(배틀러/드래곤본즈 식별용) 용도로 쓰이고 있어
     * 얼굴에 재사용할 수 없다. 턴 순서 큐(TurnOrderStrip)에서 육각형 안에 적
     * 얼굴을 보여주는 데 쓴다.
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('enemy_face', 100)->nullable()->after('hud_sprite');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('enemy_face');
        });
    }
};
