<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mz_project/data/Classes.json에서 그대로 임포트 - id를 원본과 1:1로 고정 유지한다
     * (Skills.json 등 다른 데이터가 classId로 직접 참조하기 때문에 auto-increment로
     * 새로 매기면 참조가 깨진다). MzImportSeeder가 채운다.
     */
    public function up(): void
    {
        Schema::create('mz_classes', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name', 50);
            $table->text('note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mz_classes');
    }
};
