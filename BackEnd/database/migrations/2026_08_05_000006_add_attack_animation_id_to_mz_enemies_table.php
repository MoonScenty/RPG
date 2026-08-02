<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enemies.json의 attackAnimationId(RPGEditor 적 편집 화면의 "공격 애니메이션"
     * AnimationField 필드) - 예전엔 note의 <AttackAnimation: n> 노트태그로만 설정
     * 가능했는데, 스킬 쪽처럼 구조화 필드 + 피커 UI로 옮겼다(MzNoteTagParser::
     * parseEnemyTags()에서 제거됨). mz_enemies는 원본 보관용, 실제 전투에서
     * 쓰이는 값은 units.weapon_animation_id(EnemySeeder::syncEnemyUnits()).
     */
    public function up(): void
    {
        Schema::table('mz_enemies', function (Blueprint $table) {
            $table->unsignedInteger('attack_animation_id')->nullable()->after('face_name');
        });
    }

    public function down(): void
    {
        Schema::table('mz_enemies', function (Blueprint $table) {
            $table->dropColumn('attack_animation_id');
        });
    }
};
