<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 연구소(포션류)/대장간(무기·방어구) 제작 슬롯. 계정당 작업장별 5개(CraftingController::
 * SLOT_CAP)까지 동시 진행 가능 - 슬롯 번호를 따로 두지 않고 workshop별 활성 행
 * 개수로 카운트한다. 제작 시작 시점에 골드/재료를 이미 차감해두고(CraftingController::
 * start()), finishes_at이 지나면 collect()로 수령하면서 행을 지운다(수령 즉시
 * 슬롯이 다시 빈다).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crafting_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('workshop', ['lab', 'smithy']);
            $table->enum('recipe_type', ['item', 'weapon', 'armor']);
            $table->unsignedInteger('recipe_id');
            $table->timestamp('finishes_at');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crafting_jobs');
    }
};
