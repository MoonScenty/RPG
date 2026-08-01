<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mz_project/data/Actors.json에서 그대로 임포트 - id 고정 유지. mz_enemies와
     * 같은 패턴: mz_classes/FK 관계 없이 원본 필드를 순수 보관만 하는 창고 테이블
     * (class_id도 정수 그대로 저장 - mz_classes 참조 무결성은 강제하지 않음, 참조
     * 검증은 units 파생 쪽인 ActorSeeder가 담당). equips/traits는 아직 어디서도
     * 안 쓰지만(장비 시스템 없음) 나중을 위해 원본 그대로 보관한다.
     */
    public function up(): void
    {
        Schema::create('mz_actors', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name', 50);
            $table->unsignedTinyInteger('class_id');
            $table->string('character_name', 50);
            $table->unsignedTinyInteger('character_index');
            $table->string('face_name', 50);
            $table->unsignedTinyInteger('face_index');
            $table->string('battler_name', 50)->nullable();
            $table->unsignedTinyInteger('initial_level')->default(1);
            $table->unsignedTinyInteger('max_level')->default(99);
            $table->string('nickname', 50)->nullable();
            $table->text('note')->nullable();
            $table->text('profile')->nullable();
            // equips: [무기, 방패, 머리, 몸, 장신구] mz_weapons/mz_armors id 배열(MZ 표준 순서).
            $table->json('equips');
            $table->json('traits');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mz_actors');
    }
};
