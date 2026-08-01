<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * mz_project/data/Items.json에서 그대로 임포트 - id 고정 유지. 구조가 mz_skills와
     * 거의 같다(scope/damage/effects 등 - MZ에서 아이템도 스킬과 같은 "사용 가능한
     * 것" 베이스를 공유). 아직 인벤토리/아이템 사용 시스템 자체가 없어 데이터만
     * 원본 그대로 보관한다.
     */
    public function up(): void
    {
        Schema::create('mz_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name', 50);
            $table->unsignedTinyInteger('item_type_id')->default(0);
            $table->boolean('consumable')->default(true);
            $table->unsignedInteger('price')->default(0);
            $table->unsignedSmallInteger('icon_index')->default(0);
            $table->unsignedSmallInteger('animation_id')->default(0);
            $table->unsignedTinyInteger('scope');
            $table->unsignedTinyInteger('occasion');
            $table->unsignedTinyInteger('hit_type');
            $table->unsignedTinyInteger('speed')->default(0);
            $table->unsignedTinyInteger('success_rate')->default(100);
            $table->unsignedTinyInteger('repeats')->default(1);
            $table->unsignedInteger('tp_gain')->default(0);
            $table->unsignedTinyInteger('damage_type');
            $table->string('damage_formula', 255);
            $table->unsignedTinyInteger('variance')->default(20);
            $table->integer('element_id')->default(0);
            $table->text('description')->nullable();
            $table->text('note')->nullable();
            $table->json('effects');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mz_items');
    }
};
