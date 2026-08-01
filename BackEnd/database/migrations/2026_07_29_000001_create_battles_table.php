<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 계정당 진행 중인 전투는 한 번에 하나 - "시험전투" 재접속 시 이어보기 용도. */
    public function up(): void
    {
        Schema::create('battles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['in_progress', 'finished'])->default('in_progress');
            $table->enum('winner_side', ['ally', 'enemy'])->nullable();
            $table->unsignedInteger('turn_number')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battles');
    }
};
