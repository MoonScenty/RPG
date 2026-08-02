<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Actors.json의 hudFaceName("Actor1_1" 형식)을 sprite와 동일하게 "Actor1:0"
     * (얼굴시트 파일명:faceIndex)로 변환해 저장 - 파티 HUD 좌측 얼굴 그래픽 전용,
     * sprite(대화창 등 다른 곳에서 쓰는 얼굴)와 다른 에셋으로 나중에 갈라질 수 있어
     * 별도 컬럼으로 분리한다. 장비 등으로 안 바뀌는 값이라 sprite와 마찬가지로
     * battle_units에는 복제하지 않고 units에서 그때그때 조인해서 읽는다
     * (BattleEngine::getState() 참고).
     */
    public function up(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->string('hud_sprite', 150)->nullable()->after('sprite');
        });
    }

    public function down(): void
    {
        Schema::table('units', function (Blueprint $table) {
            $table->dropColumn('hud_sprite');
        });
    }
};
