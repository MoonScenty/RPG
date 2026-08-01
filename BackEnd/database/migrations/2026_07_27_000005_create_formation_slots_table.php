<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 계정당 5개의 스왑 가능한 진형 프리셋(1~6번 슬롯: 1-3 전열, 4-6 후열).
     * 어느 프리셋이 실제 전투에 쓰이는지는 users.active_formation_preset이 가리킨다.
     */
    public function up(): void
    {
        Schema::create('formation_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('preset_number');
            $table->unsignedTinyInteger('slot');
            $table->foreignId('unit_id')->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'preset_number', 'slot']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('active_formation_preset')->default(1)->after('gold');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('active_formation_preset');
        });
        Schema::dropIfExists('formation_slots');
    }
};
