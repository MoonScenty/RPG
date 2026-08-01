<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mz_project/data/Skills.json(id 2-131, 구분선 제외)에서 그대로 임포트 - id 고정
     * 유지(effects[].dataId, Classes.json learnings[].skillId가 참조). class_id가
     * null이면 특정 직업 소속이 아닌 공용 스킬(현재 "자리 변경" 1개).
     *
     * tags: README ### 노트태그 표와 1:1 대응하는 파싱 결과 - 상태를 가리키는 값은
     * 전부 States.json의 name(숫자 아님, 프로젝트 표준 컨벤션). MzNoteTagParser 참고.
     */
    public function up(): void
    {
        Schema::create('mz_skills', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            // foreignId()는 항상 unsignedBigInteger라 mz_classes.id(unsignedTinyInteger)와
            // 타입이 안 맞아 FK 생성이 실패한다 - 타입을 맞춰서 직접 선언.
            $table->unsignedTinyInteger('class_id')->nullable();
            $table->foreign('class_id')->references('id')->on('mz_classes')->nullOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('mp_cost')->default(0);
            $table->unsignedInteger('tp_cost')->default(0);
            $table->unsignedTinyInteger('scope');
            $table->unsignedTinyInteger('hit_type');
            $table->unsignedTinyInteger('occasion');
            $table->unsignedTinyInteger('damage_type');
            $table->string('damage_formula', 255);
            $table->unsignedTinyInteger('variance')->default(20);
            $table->integer('element_id')->default(0);
            $table->unsignedTinyInteger('repeats')->default(1);
            $table->unsignedTinyInteger('success_rate')->default(100);
            $table->json('tags');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mz_skills');
    }
};
