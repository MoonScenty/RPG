<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** 계정(용병단) 단위 방어구 인벤토리 - user_weapons와 완전히 동일한 패턴. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_armors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('mz_armor_id');
            $table->foreign('mz_armor_id')->references('id')->on('mz_armors')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(0);
            $table->unique(['user_id', 'mz_armor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_armors');
    }
};
