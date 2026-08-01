<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** mz_project/data/Troops.json에서 그대로 임포트 - id 고정 유지. */
    public function up(): void
    {
        Schema::create('mz_troops', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name', 50);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mz_troops');
    }
};
