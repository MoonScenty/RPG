<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mz_project/data/States.json(id 1-200)에서 그대로 임포트 - id 고정 유지(스킬
     * 노트태그/effects가 이름으로 참조하지만, effects[].code21.dataId는 숫자라 임포트
     * 시점에 여기 id로 조회해서 이름으로 바꿔둔다). id<=100은 디버프, >100은 버프.
     */
    public function up(): void
    {
        Schema::create('mz_states', function (Blueprint $table) {
            $table->unsignedSmallInteger('id')->primary();
            $table->string('name', 50);
            $table->text('note')->nullable();
            $table->boolean('is_buff');
            $table->json('tags');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mz_states');
    }
};
