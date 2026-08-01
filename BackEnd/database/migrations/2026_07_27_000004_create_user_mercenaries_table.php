<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_mercenaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            // 이 용병의 5개 gambit 프리셋 중 전투에 실제로 쓰이는 번호.
            $table->unsignedTinyInteger('active_gambit_preset')->default(1);
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_mercenaries');
    }
};
